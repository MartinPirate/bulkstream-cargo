<?php

namespace App\Terminal;

use App\Models\Tank;
use Illuminate\Support\Facades\DB;
use LogicException;
use MartinKiragu\TankTerminal\Contracts\TankRepository;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Domain\TankState;

/**
 * MySQL adapter for the terminal core's TankRepository port.
 *
 * lockForUpdate() issues SELECT ... FOR UPDATE, so a second transaction that
 * targets the same tank blocks until the first commits and then reads the
 * already-updated balance. That is what makes concurrent allocations safe.
 */
class EloquentTankRepository implements TankRepository
{
    public function lockForUpdate(int|string $tankId): TankState
    {
        $this->assertInTransaction();

        /** @var Tank $tank */
        $tank = Tank::query()->whereKey($tankId)->lockForUpdate()->firstOrFail();

        return new TankState(
            id: $tank->id,
            code: $tank->code,
            commodity: $tank->commodity,
            capacity: Quantity::kg($tank->capacity_kg),
            balance: Quantity::kg($tank->balance_kg),
        );
    }

    public function persist(TankState $tank): void
    {
        $this->assertInTransaction();

        // Query-builder write on purpose: the TankObserver forbids balance changes
        // through the model, so this adapter is the only path that can move stock.
        DB::table('tanks')->where('id', $tank->id)->update([
            'balance_kg' => $tank->balance()->kilograms,
            'updated_at' => now(),
        ]);
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Tank balances may only be read for update inside a transaction.');
        }
    }
}
