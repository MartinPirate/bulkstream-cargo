<?php

namespace App\Terminal;

use App\Enums\MovementStatus;
use App\Models\CargoMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MartinKiragu\TankTerminal\Contracts\MovementLedger;
use MartinKiragu\TankTerminal\Domain\CargoMovement as DomainMovement;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Exceptions\TerminalException;

/**
 * Persists engine outcomes onto cargo_movements rows.
 *
 * Status writes go through the query builder (not the model) because the
 * CargoMovementObserver treats processed rows as immutable; this class is the
 * one sanctioned writer of processing outcomes.
 */
class EloquentMovementLedger implements MovementLedger
{
    public function claim(DomainMovement $movement): bool
    {
        // Eloquent's value() applies the model cast, so this is a MovementStatus (or null).
        $status = CargoMovement::query()
            ->whereKey($movement->id)
            ->lockForUpdate()
            ->value('status');

        return $status === MovementStatus::Pending;
    }

    public function markApplied(DomainMovement $movement, Quantity $net, Quantity $loss, Quantity $balanceAfter): void
    {
        DB::table('cargo_movements')->where('id', $movement->id)->update([
            'status' => MovementStatus::Applied->value,
            'net_kg' => $net->kilograms,
            'loss_kg' => $loss->kilograms,
            'balance_after_kg' => $balanceAfter->kilograms,
            'rejection_reason' => null,
            'processed_at' => now(),
            'updated_at' => now(),
        ]);

        activity('movements')
            ->performedOn(CargoMovement::find($movement->id))
            ->withProperties([
                'gross_kg' => $movement->quantity->kilograms,
                'loss_kg' => $loss->kilograms,
                'net_kg' => $net->kilograms,
                'balance_after_kg' => $balanceAfter->kilograms,
            ])
            ->log('applied');
    }

    public function markRejected(DomainMovement $movement, TerminalException $reason): void
    {
        $updated = DB::table('cargo_movements')
            ->where('id', $movement->id)
            ->where('status', MovementStatus::Pending->value)
            ->update([
                'status' => MovementStatus::Rejected->value,
                'rejection_reason' => $reason->getMessage(),
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

        Log::warning('Cargo movement rejected by terminal rule', [
            'movement_id' => $movement->id,
            'rule' => class_basename($reason),
            'reason' => $reason->getMessage(),
        ]);

        if ($updated > 0) {
            activity('movements')
                ->performedOn(CargoMovement::find($movement->id))
                ->withProperties(['rule' => class_basename($reason), 'reason' => $reason->getMessage()])
                ->log('rejected');
        }
    }
}
