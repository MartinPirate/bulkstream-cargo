<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Contracts;

use MartinKiragu\TankTerminal\Domain\TankState;

/**
 * Storage port for tank balances.
 *
 * Implementations MUST take an exclusive row lock in lockForUpdate() that is
 * held until the surrounding transaction commits; this is what serialises
 * concurrent movements against the same tank.
 */
interface TankRepository
{
    public function lockForUpdate(int|string $tankId): TankState;

    public function persist(TankState $tank): void;
}
