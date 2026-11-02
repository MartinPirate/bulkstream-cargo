<?php

namespace Tests\Feature\Terminal;

use App\Enums\MovementStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessCargoPipeline;
use App\Models\CargoMovement;
use App\Settings\TerminalSettings;
use App\Terminal\CargoPipeline;
use App\Terminal\PipelineSimulator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use LogicException;
use MartinKiragu\TankTerminal\Enums\Commodity;
use Tests\TestCase;

class CargoPipelineTest extends TestCase
{
    use RefreshDatabase;
    use TerminalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // drive the pipeline explicitly in tests
    }

    private function pipeline(): CargoPipeline
    {
        return app(CargoPipeline::class);
    }

    public function test_inbound_discharge_applies_net_of_half_percent_transit_loss(): void
    {
        $tank = $this->makeTank(10_000);
        $movement = $this->discharge($tank, $this->makeVessel(), 5_000, now()->subHour());

        $this->pipeline()->processPending();

        $movement->refresh();
        $this->assertSame(MovementStatus::Applied, $movement->status);
        $this->assertSame(25_000, $movement->loss_kg);
        $this->assertSame(4_975_000, $movement->net_kg);
        $this->assertSame(4_975_000, $tank->fresh()->balance_kg);
    }

    public function test_overflow_is_rejected_with_a_domain_reason_and_balance_is_untouched(): void
    {
        $tank = $this->makeTank(1_000);
        $vessel = $this->makeVessel();
        $this->discharge($tank, $vessel, 900, now()->subHours(2));
        $overflow = $this->discharge($tank, $vessel, 200, now()->subHour());

        $this->pipeline()->processPending();

        $overflow->refresh();
        $this->assertSame(MovementStatus::Rejected, $overflow->status);
        $this->assertStringContainsString('overflow blocked', $overflow->rejection_reason);
        $this->assertSame(895_500, $tank->fresh()->balance_kg);
    }

    public function test_events_logged_out_of_order_are_applied_in_event_time_order(): void
    {
        $tank = $this->makeTank(1_000);
        $vessel = $this->makeVessel();

        // The truck's ticket reaches us first, but the ship discharged two hours earlier.
        // (50 MT stays under the 100 MT approval threshold, so it goes straight to the pipeline.)
        $truck = $this->load($tank, 50, now()->subHour());
        $ship = $this->discharge($tank, $vessel, 500, now()->subHours(3));

        $report = $this->pipeline()->processPending();

        $this->assertSame([$ship->id, $truck->id], $report->processingOrder);
        $this->assertSame(MovementStatus::Applied, $truck->fresh()->status);
        $this->assertSame(497_500, $ship->fresh()->balance_after_kg);
        $this->assertSame(447_500, $truck->fresh()->balance_after_kg);
    }

    public function test_reprocessing_never_double_applies(): void
    {
        $tank = $this->makeTank(1_000);
        $this->discharge($tank, $this->makeVessel(), 100, now()->subHour());

        $this->pipeline()->processPending();
        $this->pipeline()->processPending();

        $this->assertSame(99_500, $tank->fresh()->balance_kg);
    }

    public function test_new_pending_movements_dispatch_the_pipeline_job(): void
    {
        $this->discharge($this->makeTank(), $this->makeVessel(), 10, now());

        Queue::assertPushed(ProcessCargoPipeline::class);
    }

    public function test_wrong_commodity_feed_is_rejected(): void
    {
        $tank = $this->makeTank(1_000, Commodity::CPOL);
        $movement = $this->discharge($tank, $this->makeVessel(), 10, now());
        DB::table('cargo_movements')->where('id', $movement->id)->update(['commodity' => Commodity::PFAD->value]);

        $this->pipeline()->processPending();

        $this->assertSame(MovementStatus::Rejected, $movement->fresh()->status);
        $this->assertSame(0, $tank->fresh()->balance_kg);
    }

    public function test_large_release_waits_for_a_supervisor_and_respects_maker_checker(): void
    {
        $operator = $this->makeUser(UserRole::Operator);
        $supervisor = $this->makeUser(UserRole::Supervisor);
        $tank = $this->makeTank(5_000);
        $this->discharge($tank, $this->makeVessel(), 1_000, now()->subDay());
        $this->pipeline()->processPending();

        Auth::login($operator);
        $release = $this->load($tank, 200, now()); // above the 100 MT default threshold
        Auth::logout();

        $this->assertSame(MovementStatus::AwaitingApproval, $release->status);
        $this->pipeline()->processPending();
        $this->assertSame(995_000, $tank->fresh()->balance_kg, 'Unapproved release must not move stock.');

        // An operator cannot approve.
        try {
            $this->pipeline()->approve($release, $operator);
            $this->fail('Operator approval should be refused.');
        } catch (AuthorizationException) {
        }

        $this->pipeline()->approve($release, $supervisor, 'ok');
        $this->pipeline()->processPending();

        $this->assertSame(MovementStatus::Applied, $release->fresh()->status);
        $this->assertSame($supervisor->id, $release->fresh()->decided_by);
        $this->assertSame(795_000, $tank->fresh()->balance_kg);
    }

    public function test_supervisor_cannot_approve_their_own_release(): void
    {
        $supervisor = $this->makeUser(UserRole::Supervisor);
        $tank = $this->makeTank(5_000);

        Auth::login($supervisor);
        $release = $this->load($tank, 500, now());
        Auth::logout();

        $this->expectException(AuthorizationException::class);
        $this->pipeline()->approve($release, $supervisor);
    }

    public function test_threshold_comes_from_settings(): void
    {
        app(TerminalSettings::class)->update(['release_approval_threshold_kg' => 1_000_000]);
        $release = $this->load($this->makeTank(5_000), 200, now());

        $this->assertSame(MovementStatus::Pending, $release->status);
    }

    public function test_tank_balance_cannot_be_edited_through_the_model(): void
    {
        $tank = $this->makeTank();

        $this->expectException(LogicException::class);
        $tank->balance_kg = 500_000;
        $tank->save();
    }

    public function test_applied_movements_are_immutable(): void
    {
        $movement = $this->discharge($this->makeTank(), $this->makeVessel(), 10, now());
        $this->pipeline()->processPending();

        $this->expectException(LogicException::class);
        $movement->refresh();
        $movement->gross_kg = 1;
        $movement->save();
    }

    public function test_tank_capacity_cannot_shrink_below_stock(): void
    {
        $tank = $this->makeTank(1_000);
        $this->discharge($tank, $this->makeVessel(), 600, now());
        $this->pipeline()->processPending();

        $this->expectException(ValidationException::class);
        $tank->refresh();
        $tank->capacity_kg = 500_000;
        $tank->save();
    }

    public function test_database_refuses_an_overfilled_tank_even_if_code_is_bypassed(): void
    {
        $tank = $this->makeTank(1_000);

        $this->expectException(QueryException::class);
        DB::table('tanks')->where('id', $tank->id)->update(['balance_kg' => 1_000_001]);
    }

    public function test_simulated_feed_creates_movements_logged_out_of_event_order(): void
    {
        $this->makeTank(5_000);
        $this->makeVessel();

        $logged = app(PipelineSimulator::class)->emit(12);

        $this->assertCount(12, $logged);
        $this->assertSame(12, CargoMovement::query()->count());
    }
}
