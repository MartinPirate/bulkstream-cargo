<?php

namespace Tests\Feature\Terminal;

use App\Enums\UserRole;
use App\Enums\VesselStatus;
use App\Models\CargoMovement;
use App\Models\Tank;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Support\Carbon;
use MartinKiragu\TankTerminal\Enums\Commodity;
use MartinKiragu\TankTerminal\Enums\MovementDirection;
use Spatie\Permission\Models\Role;

trait TerminalTestHelpers
{
    protected function makeUser(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    protected function makeTank(int $capacityT = 1_000, Commodity $commodity = Commodity::CPO, string $code = 'T-101'): Tank
    {
        return Tank::query()->create([
            'code' => $code,
            'commodity' => $commodity,
            'capacity_kg' => $capacityT * 1000,
            'is_active' => true,
        ]);
    }

    protected function makeVessel(): Vessel
    {
        return Vessel::query()->create([
            'name' => 'MT Test',
            'imo_number' => (string) random_int(1_000_000, 9_999_999),
            'voyage_number' => 'V-1',
            'status' => VesselStatus::Discharging,
        ]);
    }

    protected function discharge(Tank $tank, Vessel $vessel, float $tonnes, string|Carbon $at, ?string $ref = null): CargoMovement
    {
        return CargoMovement::query()->create([
            'reference' => $ref,
            'direction' => MovementDirection::Inbound,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'gross_kg' => (int) round($tonnes * 1000),
            'occurred_at' => $at,
        ]);
    }

    protected function load(Tank $tank, float $tonnes, string|Carbon $at, string $truck = 'KDA 001A', ?string $ref = null): CargoMovement
    {
        return CargoMovement::query()->create([
            'reference' => $ref,
            'direction' => MovementDirection::Outbound,
            'tank_id' => $tank->id,
            'truck_registration' => $truck,
            'gross_kg' => (int) round($tonnes * 1000),
            'occurred_at' => $at,
        ]);
    }
}
