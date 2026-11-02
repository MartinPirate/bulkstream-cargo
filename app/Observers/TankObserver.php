<?php

namespace App\Observers;

use App\Models\Tank;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Entity state hooks for tanks.
 */
class TankObserver
{
    public function creating(Tank $tank): void
    {
        // A new tank always starts empty; stock only arrives through the pipeline.
        $tank->balance_kg = 0;
        $tank->code = strtoupper(trim($tank->code));
    }

    public function updating(Tank $tank): void
    {
        if ($tank->isDirty('balance_kg')) {
            throw new LogicException('Tank balances change only through cargo movements or approved stock corrections.');
        }

        if ($tank->isDirty('capacity_kg') && $tank->capacity_kg < $tank->balance_kg) {
            throw ValidationException::withMessages([
                'capacity_kg' => sprintf(
                    'Capacity cannot be set below the current stock of %s.',
                    $tank->balance()->format(),
                ),
            ]);
        }

        if ($tank->isDirty('commodity') && $tank->balance_kg > 0) {
            throw ValidationException::withMessages([
                'commodity' => 'Empty the tank before re-assigning it to a different commodity.',
            ]);
        }
    }

    public function deleting(Tank $tank): void
    {
        if ($tank->balance_kg > 0 || $tank->movements()->exists()) {
            throw ValidationException::withMessages([
                'tank' => 'Tanks with stock or movement history cannot be deleted. Deactivate the tank instead.',
            ]);
        }
    }
}
