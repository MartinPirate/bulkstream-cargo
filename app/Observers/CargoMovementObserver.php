<?php

namespace App\Observers;

use App\Enums\MovementStatus;
use App\Jobs\ProcessCargoPipeline;
use App\Models\CargoMovement;
use App\Models\Tank;
use App\Models\Vessel;
use App\Settings\TerminalSettings;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use MartinKiragu\TankTerminal\Enums\MovementDirection;

/**
 * Entity state hooks for cargo movements.
 *
 * creating: normalise, validate the counter-party and route large releases to approval.
 * created:  hand pending movements to the asynchronous pipeline (after commit).
 * updating/deleting: processed rows are an immutable ledger.
 */
class CargoMovementObserver
{
    public function __construct(private readonly TerminalSettings $settings) {}

    public function creating(CargoMovement $movement): void
    {
        $movement->reference = $movement->reference
            ? strtoupper(trim($movement->reference))
            : 'MAN-'.strtoupper((string) Str::ulid());
        $movement->received_at = now();
        $movement->created_by ??= auth()->id();

        $tank = Tank::query()->find($movement->tank_id);
        if ($tank === null) {
            throw ValidationException::withMessages(['tank_id' => 'Select a valid tank.']);
        }

        // The tank decides the commodity; a mismatching feed is caught by the engine.
        $movement->commodity ??= $tank->commodity;

        if ($movement->direction === MovementDirection::Inbound) {
            $vessel = Vessel::query()->find($movement->vessel_id);
            if ($vessel === null) {
                throw ValidationException::withMessages(['vessel_id' => 'Inbound flows must come from a vessel.']);
            }
            $movement->truck_registration = null;
        } else {
            if (blank($movement->truck_registration)) {
                throw ValidationException::withMessages(['truck_registration' => 'Outbound flows must name a truck.']);
            }
            $movement->truck_registration = strtoupper(preg_replace('/\s+/', ' ', trim($movement->truck_registration)));
            $movement->vessel_id = null;
        }

        $movement->status = $this->requiresApproval($movement)
            ? MovementStatus::AwaitingApproval
            : MovementStatus::Pending;
    }

    public function created(CargoMovement $movement): void
    {
        if ($movement->status === MovementStatus::Pending) {
            ProcessCargoPipeline::dispatch()->afterCommit();
        }
    }

    public function updating(CargoMovement $movement): void
    {
        $original = MovementStatus::from($movement->getRawOriginal('status'));

        if ($original !== MovementStatus::AwaitingApproval) {
            throw new LogicException("Movement {$movement->reference} is {$original->value} and can no longer be edited.");
        }
    }

    public function deleting(CargoMovement $movement): void
    {
        if (! in_array($movement->status, [MovementStatus::AwaitingApproval, MovementStatus::Declined], true)) {
            throw ValidationException::withMessages([
                'movement' => 'Only releases that never reached a tank can be deleted.',
            ]);
        }
    }

    private function requiresApproval(CargoMovement $movement): bool
    {
        return $movement->direction === MovementDirection::Outbound
            && $movement->gross_kg >= $this->settings->releaseApprovalThresholdKg();
    }
}
