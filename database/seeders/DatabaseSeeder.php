<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\VesselStatus;
use App\Models\CargoMovement;
use App\Models\Tank;
use App\Models\User;
use App\Models\Vessel;
use App\Terminal\CargoPipeline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use MartinKiragu\TankTerminal\Enums\Commodity;
use MartinKiragu\TankTerminal\Enums\MovementDirection;
use Spatie\Permission\Models\Role;

/**
 * Demo terminal: 9 tanks across three commodities, four vessels and a week of
 * discharges and truck loadings. Some events are deliberately logged out of
 * chronological order to exercise the sequencer.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        $password = (string) env('DEMO_PASSWORD', 'change-me-'.bin2hex(random_bytes(4)));

        $this->user('Terminal Admin', 'admin@bulkstream.test', UserRole::Admin, $password);
        $this->user('Shift Supervisor', 'supervisor@bulkstream.test', UserRole::Supervisor, $password);
        $operator = $this->user('Tank Farm Operator', 'operator@bulkstream.test', UserRole::Operator, $password);

        $tanks = collect([
            ['T-101', Commodity::CPO, 5_000, 52.5],
            ['T-102', Commodity::CPO, 5_000, 53.0],
            ['T-103', Commodity::CPO, 3_500, 51.8],
            ['T-201', Commodity::CPOL, 3_000, 30.0],
            ['T-202', Commodity::CPOL, 3_000, 29.5],
            ['T-203', Commodity::CPOL, 2_000, 31.2],
            ['T-301', Commodity::PFAD, 2_000, 64.0],
            ['T-302', Commodity::PFAD, 1_500, 58.4],
            ['T-303', Commodity::PFAD, 1_500, null],
        ])->mapWithKeys(fn (array $t) => [$t[0] => Tank::query()->updateOrCreate(
            ['code' => $t[0]],
            ['commodity' => $t[1], 'capacity_kg' => $t[2] * 1000, 'temperature_c' => $t[3], 'is_active' => true],
        )]);

        $seaPalm = Vessel::query()->updateOrCreate(['imo_number' => '9412345'], [
            'name' => 'MT Sea Palm', 'voyage_number' => 'SP-2611', 'status' => VesselStatus::Discharging,
            'eta' => now()->subDays(6), 'berthed_at' => now()->subDays(6),
        ]);
        $kotaHarum = Vessel::query()->updateOrCreate(['imo_number' => '9523456'], [
            'name' => 'MT Kota Harum', 'voyage_number' => 'KH-0412', 'status' => VesselStatus::Berthed,
            'eta' => now()->subDays(2), 'berthed_at' => now()->subDay(),
        ]);
        Vessel::query()->updateOrCreate(['imo_number' => '9634567'], [
            'name' => 'MT Nordic Ace', 'voyage_number' => 'NA-1177', 'status' => VesselStatus::Expected,
            'eta' => now()->addDays(3),
        ]);
        Vessel::query()->updateOrCreate(['imo_number' => '9345678'], [
            'name' => 'MT Ocean Bloom', 'voyage_number' => 'OB-0930', 'status' => VesselStatus::Departed,
            'eta' => now()->subDays(20), 'berthed_at' => now()->subDays(19), 'departed_at' => now()->subDays(16),
        ]);

        if (CargoMovement::query()->exists()) {
            return;
        }

        Auth::login($operator);

        $base = Carbon::now()->subDays(6)->startOfDay()->addHours(6);
        $events = [
            // Vessel discharges (gross, as metered off the ship)
            [MovementDirection::Inbound, 'T-101', $seaPalm, null, 4_200, 0],
            [MovementDirection::Inbound, 'T-102', $seaPalm, null, 3_100, 3],
            [MovementDirection::Inbound, 'T-201', $seaPalm, null, 2_600, 5],
            [MovementDirection::Inbound, 'T-301', $seaPalm, null, 1_400, 8],
            [MovementDirection::Inbound, 'T-202', $kotaHarum, null, 1_900, 30],
            [MovementDirection::Inbound, 'T-302', $kotaHarum, null, 900, 33],
            [MovementDirection::Inbound, 'T-103', $kotaHarum, null, 2_800, 36],
            // Truck loadings
            [MovementDirection::Outbound, 'T-101', null, 'KDA 412X', 34, 12],
            [MovementDirection::Outbound, 'T-101', null, 'KCZ 908M', 33, 14],
            [MovementDirection::Outbound, 'T-201', null, 'KDB 220L', 30, 15],
            [MovementDirection::Outbound, 'T-301', null, 'KCX 771A', 28, 18],
            [MovementDirection::Outbound, 'T-102', null, 'KDA 655Q', 35, 40],
            [MovementDirection::Outbound, 'T-202', null, 'KDC 104T', 30, 44],
            [MovementDirection::Outbound, 'T-302', null, 'KCY 330B', 26, 50],
            // Blocked: would overflow T-301 (2,000 MT tank already ~1,365 MT)
            [MovementDirection::Inbound, 'T-301', $kotaHarum, null, 900, 52],
            // Blocked: truck tries to load from T-203, which is empty
            [MovementDirection::Outbound, 'T-203', null, 'KDD 019P', 30, 55],
        ];

        // Shuffle insertion order so received_at is out of step with occurred_at,
        // as it is when meter, weighbridge and ship logs arrive asynchronously.
        foreach (collect($events)->shuffle(42)->values() as $i => [$direction, $tank, $vessel, $truck, $tonnes, $hours]) {
            CargoMovement::query()->create([
                'reference' => sprintf('%s-%04d', $direction === MovementDirection::Inbound ? 'DSC' : 'LDG', $i + 1),
                'direction' => $direction,
                'tank_id' => $tanks[$tank]->id,
                'vessel_id' => $vessel?->id,
                'truck_registration' => $truck,
                'gross_kg' => $tonnes * 1000,
                'occurred_at' => $base->copy()->addHours($hours)->addMinutes(($i * 7) % 50),
            ]);
        }

        // One large release that needs a supervisor (above the 100 MT threshold).
        CargoMovement::query()->create([
            'reference' => 'LDG-BULK-0001',
            'direction' => MovementDirection::Outbound,
            'tank_id' => $tanks['T-102']->id,
            'truck_registration' => 'KDE 500F',
            'gross_kg' => 450_000,
            'occurred_at' => now()->subHours(2),
        ]);

        Auth::logout();

        app(CargoPipeline::class)->processPending();
    }

    private function user(string $name, string $email, UserRole $role, string $password): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password;
        $user->email_verified_at = now();
        $user->save();
        $user->syncRoles([$role->value]);

        return $user;
    }
}
