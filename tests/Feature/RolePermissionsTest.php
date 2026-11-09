<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\CargoMovements\Pages\ListCargoMovements;
use App\Models\CargoMovement;
use App\Models\StockCorrection;
use App\Models\User;
use App\Terminal\CargoPipeline;
use App\Terminal\StockCorrectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\Feature\Terminal\TerminalTestHelpers;
use Tests\TestCase;

/**
 * The README role matrix, row by row: every allowed role gets in, and the role
 * just below it is refused. Maker-checker is checked for every approving role.
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;
    use TerminalTestHelpers;

    private User $operator;

    private User $supervisor;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->operator = $this->makeUser(UserRole::Operator);
        $this->supervisor = $this->makeUser(UserRole::Supervisor);
        $this->admin = $this->makeUser(UserRole::Admin);
    }

    public function test_every_role_records_movements_and_requests_corrections(): void
    {
        foreach ([$this->operator, $this->supervisor, $this->admin] as $user) {
            $this->assertTrue($user->can('create', CargoMovement::class));
            $this->assertTrue($user->can('create', StockCorrection::class));
            $this->actingAs($user)->get('/admin/cargo-movements/create')->assertOk();
            $this->actingAs($user)->get('/admin/stock-corrections/create')->assertOk();
        }
    }

    public function test_only_supervisors_and_admins_approve_and_never_their_own_release(): void
    {
        $tank = $this->makeTank(5_000);
        $this->discharge($tank, $this->makeVessel(), 1_000, now()->subDay());
        app(CargoPipeline::class)->processPending();

        foreach ([$this->operator, $this->supervisor, $this->admin] as $maker) {
            Auth::login($maker);
            $release = $this->load($tank, 150, now(), 'KDA '.$maker->id.'A');
            Auth::logout();

            $this->assertFalse($maker->can('approve', $release), 'Nobody approves their own release.');
            $this->assertFalse($this->operator->can('approve', $release), 'Operators never approve.');

            foreach ([$this->supervisor, $this->admin] as $checker) {
                if ($checker->isNot($maker)) {
                    $this->assertTrue($checker->can('approve', $release));
                }
            }
        }
    }

    public function test_only_supervisors_and_admins_approve_corrections_and_never_their_own(): void
    {
        $tank = $this->makeTank();
        $service = app(StockCorrectionService::class);

        foreach ([$this->operator, $this->supervisor, $this->admin] as $maker) {
            $correction = $service->request($tank, 'increase', 1_000, 'Dip survey reads high', $maker);

            $this->assertFalse($maker->can('approve', $correction));
            $this->assertFalse($this->operator->can('approve', $correction));

            foreach ([$this->supervisor, $this->admin] as $checker) {
                if ($checker->isNot($maker)) {
                    $this->assertTrue($checker->can('approve', $correction));
                }
            }
        }
    }

    public function test_pending_release_is_editable_by_its_author_or_an_admin_only(): void
    {
        $tank = $this->makeTank(5_000);
        $this->discharge($tank, $this->makeVessel(), 1_000, now()->subDay());
        app(CargoPipeline::class)->processPending();

        Auth::login($this->operator);
        $release = $this->load($tank, 150, now());
        Auth::logout();

        $this->assertTrue($this->operator->can('update', $release));
        $this->assertTrue($this->admin->can('update', $release));
        $this->assertFalse($this->supervisor->can('update', $release));
        $this->assertFalse($this->makeUser(UserRole::Operator)->can('update', $release));
    }

    public function test_vessels_are_managed_by_supervisors_and_admins(): void
    {
        $this->actingAs($this->operator)->get('/admin/vessels')->assertOk();
        $this->actingAs($this->operator)->get('/admin/vessels/create')->assertForbidden();
        $this->actingAs($this->supervisor)->get('/admin/vessels/create')->assertOk();
        $this->actingAs($this->admin)->get('/admin/vessels/create')->assertOk();
    }

    public function test_audit_log_is_for_supervisors_and_admins(): void
    {
        $this->actingAs($this->operator)->get('/admin/audit-logs')->assertForbidden();
        $this->actingAs($this->supervisor)->get('/admin/audit-logs')->assertOk();
        $this->actingAs($this->admin)->get('/admin/audit-logs')->assertOk();
    }

    public function test_simulate_and_process_buttons_are_hidden_from_operators(): void
    {
        $this->actingAs($this->operator);
        Livewire::test(ListCargoMovements::class)
            ->assertActionHidden('simulate')
            ->assertActionHidden('process');

        foreach ([$this->supervisor, $this->admin] as $user) {
            $this->actingAs($user);
            Livewire::test(ListCargoMovements::class)
                ->assertActionVisible('simulate')
                ->assertActionVisible('process');
        }
    }

    public function test_tanks_and_commodity_profiles_are_view_only_below_admin(): void
    {
        $tank = $this->makeTank();

        foreach ([$this->operator, $this->supervisor] as $user) {
            $this->actingAs($user)->get('/admin/tanks')->assertOk();
            $this->actingAs($user)->get('/admin/tanks/create')->assertForbidden();
            $this->actingAs($user)->get("/admin/tanks/{$tank->id}/edit")->assertForbidden();
            $this->actingAs($user)->get('/admin/commodity-profiles')->assertOk();
        }

        $this->actingAs($this->admin)->get("/admin/tanks/{$tank->id}/edit")->assertOk();
    }

    public function test_only_empty_tanks_can_be_deleted_and_only_by_admins(): void
    {
        $empty = $this->makeTank(1_000, code: 'T-201');
        $full = $this->makeTank(1_000, code: 'T-202');
        $this->discharge($full, $this->makeVessel(), 100, now()->subMinute());
        app(CargoPipeline::class)->processPending();
        $full->refresh();

        $this->assertTrue($this->admin->can('delete', $empty));
        $this->assertFalse($this->admin->can('delete', $full));
        $this->assertFalse($this->supervisor->can('delete', $empty));
    }

    public function test_users_and_settings_are_admin_only(): void
    {
        foreach ([$this->operator, $this->supervisor] as $user) {
            $this->actingAs($user)->get('/admin/users')->assertForbidden();
            $this->actingAs($user)->get('/admin/manage-settings')->assertForbidden();
        }

        $this->actingAs($this->admin)->get('/admin/users')->assertOk();
        $this->actingAs($this->admin)->get('/admin/manage-settings')->assertOk();
        $this->assertFalse($this->admin->can('delete', $this->admin), 'Admins cannot delete themselves.');
    }

    public function test_refusals_and_missing_records_render_the_branded_error_pages(): void
    {
        $this->actingAs($this->operator)->get('/admin/users')
            ->assertForbidden()
            ->assertSee('This area is outside your role')
            ->assertSee('You are signed in as <strong>Operator</strong>', false);

        $this->actingAs($this->admin)->get('/admin/tanks/999999/edit')
            ->assertNotFound()
            ->assertSee('Nothing in this tank');
    }

    public function test_processed_movements_cannot_be_edited_or_deleted_by_anyone(): void
    {
        $tank = $this->makeTank();
        $movement = $this->discharge($tank, $this->makeVessel(), 100, now()->subMinute());
        app(CargoPipeline::class)->processPending();
        $movement->refresh();

        foreach ([$this->operator, $this->supervisor, $this->admin] as $user) {
            $this->assertFalse($user->can('update', $movement));
            $this->assertFalse($user->can('delete', $movement));
        }
    }
}
