<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Terminal\TerminalTestHelpers;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;
    use TerminalTestHelpers;

    public function test_users_without_a_terminal_role_are_refused(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_every_role_reaches_the_dashboard_and_operations_screens(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)->get('/admin')->assertOk();
            $this->actingAs($user)->get('/admin/cargo-movements')->assertOk();
            $this->actingAs($user)->get('/admin/tanks')->assertOk();
        }
    }

    public function test_only_admins_manage_users_and_settings(): void
    {
        $operator = $this->makeUser(UserRole::Operator);
        $supervisor = $this->makeUser(UserRole::Supervisor);
        $admin = $this->makeUser(UserRole::Admin);

        $this->actingAs($operator)->get('/admin/users')->assertForbidden();
        $this->actingAs($supervisor)->get('/admin/manage-settings')->assertForbidden();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/manage-settings')->assertOk();
    }

    public function test_visit_tracker_renders_only_when_configured_with_a_valid_id(): void
    {
        config(['services.marketing_engine.tracking_id' => null]);
        $this->get('/admin/login')->assertOk()->assertDontSee('visitors/track', false);

        config(['services.marketing_engine.tracking_id' => 'bulkstream_abc123']);
        $this->get('/admin/login')->assertOk()
            ->assertSee("var tid = 'bulkstream_abc123'", false)
            ->assertSee('engine.usesquareai.com\/api\/visitors\/track', false);

        config(['services.marketing_engine.tracking_id' => '"};alert(1)//']);
        $this->get('/admin/login')->assertOk()->assertDontSee('visitors/track', false);
    }

    public function test_operators_cannot_create_tanks(): void
    {
        $this->actingAs($this->makeUser(UserRole::Operator))->get('/admin/tanks/create')->assertForbidden();
        $this->actingAs($this->makeUser(UserRole::Admin))->get('/admin/tanks/create')->assertOk();
    }
}
