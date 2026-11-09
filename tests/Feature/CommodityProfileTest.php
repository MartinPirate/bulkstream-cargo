<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Support\RichOptions;
use App\Models\CommodityProfile;
use App\Models\Tank;
use App\Terminal\CargoPipeline;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use MartinKiragu\TankTerminal\Enums\Commodity;
use Tests\Feature\Terminal\TerminalTestHelpers;
use Tests\TestCase;

class CommodityProfileTest extends TestCase
{
    use RefreshDatabase;
    use TerminalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        CommodityProfile::flushMemo();
    }

    public function test_migration_seeds_one_profile_per_commodity_from_domain_defaults(): void
    {
        $this->assertSame(count(Commodity::cases()), CommodityProfile::query()->count());

        foreach (Commodity::cases() as $commodity) {
            $this->assertSame(
                array_map('floatval', $commodity->storageTemperatureRange()),
                CommodityProfile::for($commodity)->temperatureRange(),
            );
        }
    }

    public function test_tank_temperature_alert_follows_the_edited_profile_band(): void
    {
        $tank = $this->makeTank(1_000, Commodity::CPO);
        $this->discharge($tank, $this->makeVessel(), 100, now()->subMinute());
        app(CargoPipeline::class)->processPending();
        $tank->refresh();
        $tank->forceFill(['temperature_c' => 45])->saveQuietly();

        $this->assertTrue($tank->isTemperatureOutOfRange(), 'Default CPO band is 50–55 °C.');

        CommodityProfile::query()->where('code', 'cpo')->firstOrFail()
            ->update(['storage_temp_min_c' => 40, 'storage_temp_max_c' => 55]);

        $this->assertFalse($tank->isTemperatureOutOfRange(), 'Band widened to 40–55 °C.');
    }

    public function test_missing_profile_row_falls_back_to_domain_defaults(): void
    {
        DB::table('commodity_profiles')->where('code', 'pfad')->delete();
        CommodityProfile::flushMemo();

        $this->assertSame([60.0, 70.0], CommodityProfile::for(Commodity::PFAD)->temperatureRange());
    }

    public function test_database_refuses_an_inverted_temperature_band(): void
    {
        $this->expectException(QueryException::class);

        DB::table('commodity_profiles')->where('code', 'cpo')
            ->update(['storage_temp_min_c' => 60, 'storage_temp_max_c' => 50]);
    }

    public function test_everyone_sees_profiles_but_only_admins_edit_and_nobody_creates(): void
    {
        $cpo = CommodityProfile::query()->where('code', 'cpo')->firstOrFail();
        $operator = $this->makeUser(UserRole::Operator);
        $admin = $this->makeUser(UserRole::Admin);

        $this->actingAs($operator)->get('/admin/commodity-profiles')->assertOk()->assertSee('Crude Palm Olein');
        $this->actingAs($operator)->get("/admin/commodity-profiles/{$cpo->id}/edit")->assertForbidden();
        $this->actingAs($admin)->get("/admin/commodity-profiles/{$cpo->id}/edit")->assertOk();
        $this->actingAs($admin)->get('/admin/commodity-profiles/create')->assertNotFound();
    }

    public function test_rich_options_escape_admin_editable_text(): void
    {
        $this->makeTank(1_000, Commodity::CPO, '<b>T-9</b>');
        CommodityProfile::query()->where('code', 'cpol')->firstOrFail()->update(['name' => '<script>x</script>']);

        $tankHtml = implode('', RichOptions::tanks(Tank::all()));
        $commodityHtml = implode('', RichOptions::commodities());

        // TankObserver upper-cases codes; what matters is that the tags arrive escaped.
        $this->assertStringNotContainsStringIgnoringCase('<b>T-9</b>', $tankHtml);
        $this->assertStringContainsStringIgnoringCase('&lt;b&gt;T-9&lt;/b&gt;', $tankHtml);
        $this->assertStringNotContainsString('<script>', $commodityHtml);
    }

    public function test_redesigned_forms_render_for_their_roles(): void
    {
        $this->makeTank();
        $operator = $this->makeUser(UserRole::Operator);

        $this->actingAs($operator)->get('/admin/stock-corrections/create')->assertOk()->assertSee('Effect on book stock');
        $this->actingAs($operator)->get('/admin/cargo-movements/create')->assertOk();
        $this->actingAs($this->makeUser(UserRole::Admin))->get('/admin/tanks/create')->assertOk();
    }
}
