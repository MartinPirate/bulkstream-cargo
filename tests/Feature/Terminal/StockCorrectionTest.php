<?php

namespace Tests\Feature\Terminal;

use App\Enums\CorrectionStatus;
use App\Enums\UserRole;
use App\Terminal\CargoPipeline;
use App\Terminal\StockCorrectionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StockCorrectionTest extends TestCase
{
    use RefreshDatabase;
    use TerminalTestHelpers;

    private StockCorrectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->service = app(StockCorrectionService::class);
    }

    public function test_correction_changes_stock_only_after_supervisor_approval(): void
    {
        $operator = $this->makeUser(UserRole::Operator);
        $supervisor = $this->makeUser(UserRole::Supervisor);
        $tank = $this->makeTank(1_000);
        $this->discharge($tank, $this->makeVessel(), 100, now());
        app(CargoPipeline::class)->processPending();

        $correction = $this->service->request($tank, 'decrease', 2_500, 'Dip survey DS-1 reads 2.5 MT below book', $operator);
        $this->assertSame(99_500, $tank->fresh()->balance_kg);

        $this->service->approve($correction, $supervisor);

        $this->assertSame(CorrectionStatus::Applied, $correction->fresh()->status);
        $this->assertSame(97_000, $tank->fresh()->balance_kg);
        $this->assertSame(97_000, $correction->fresh()->balance_after_kg);
    }

    public function test_requester_cannot_approve_their_own_correction(): void
    {
        $supervisor = $this->makeUser(UserRole::Supervisor);
        $correction = $this->service->request($this->makeTank(), 'increase', 1_000, 'Dip survey reads high', $supervisor);

        $this->expectException(AuthorizationException::class);
        $this->service->approve($correction, $supervisor);
    }

    public function test_approved_correction_that_would_overflow_is_marked_failed_not_applied(): void
    {
        $operator = $this->makeUser(UserRole::Operator);
        $supervisor = $this->makeUser(UserRole::Supervisor);
        $tank = $this->makeTank(1);

        $correction = $this->service->request($tank, 'increase', 2_000, 'Book says more than the tank can hold', $operator);
        $result = $this->service->approve($correction, $supervisor);

        $this->assertSame(CorrectionStatus::Failed, $result->status);
        $this->assertStringContainsString('overflow', $result->decision_note);
        $this->assertSame(0, $tank->fresh()->balance_kg);
    }

    public function test_declined_correction_never_touches_stock(): void
    {
        $operator = $this->makeUser(UserRole::Operator);
        $supervisor = $this->makeUser(UserRole::Supervisor);
        $tank = $this->makeTank();

        $correction = $this->service->request($tank, 'increase', 5_000, 'Suspect meter reading', $operator);
        $this->service->decline($correction, $supervisor, 'Re-dip first');

        $this->assertSame(CorrectionStatus::Declined, $correction->fresh()->status);
        $this->assertSame(0, $tank->fresh()->balance_kg);
    }
}
