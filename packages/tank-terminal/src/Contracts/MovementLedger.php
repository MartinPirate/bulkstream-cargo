<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Contracts;

use MartinKiragu\TankTerminal\Domain\CargoMovement;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Exceptions\TerminalException;

/**
 * Records the outcome of each movement.
 */
interface MovementLedger
{
    /**
     * Lock the movement record and confirm it is still pending.
     * Returns false when another worker has already applied or rejected it,
     * which makes processing idempotent under duplicate delivery.
     */
    public function claim(CargoMovement $movement): bool;

    public function markApplied(CargoMovement $movement, Quantity $net, Quantity $loss, Quantity $balanceAfter): void;

    public function markRejected(CargoMovement $movement, TerminalException $reason): void;
}
