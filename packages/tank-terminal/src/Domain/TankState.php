<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Domain;

use MartinKiragu\TankTerminal\Enums\Commodity;
use MartinKiragu\TankTerminal\Exceptions\CommodityMismatchException;
use MartinKiragu\TankTerminal\Exceptions\InsufficientTankStockException;
use MartinKiragu\TankTerminal\Exceptions\InvalidQuantityException;
use MartinKiragu\TankTerminal\Exceptions\TankCapacityExceededException;

/**
 * In-memory view of one shore tank while it is locked for a single movement.
 *
 * All capacity and stock invariants live here, so they hold no matter which
 * entry point (dashboard, API, batch import) triggers the movement.
 */
final class TankState
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $code,
        public readonly Commodity $commodity,
        public readonly Quantity $capacity,
        private Quantity $balance,
    ) {}

    public function balance(): Quantity
    {
        return $this->balance;
    }

    public function freeCapacity(): Quantity
    {
        return $this->capacity->minus($this->balance);
    }

    public function receive(Commodity $commodity, Quantity $quantity): void
    {
        $this->guardMovement($commodity, $quantity);

        if ($quantity->isGreaterThan($this->freeCapacity())) {
            throw new TankCapacityExceededException($this->code, $this->capacity, $this->balance, $quantity);
        }

        $this->balance = $this->balance->plus($quantity);
    }

    public function release(Commodity $commodity, Quantity $quantity): void
    {
        $this->guardMovement($commodity, $quantity);

        if ($quantity->isGreaterThan($this->balance)) {
            throw new InsufficientTankStockException($this->code, $this->balance, $quantity);
        }

        $this->balance = $this->balance->minus($quantity);
    }

    private function guardMovement(Commodity $commodity, Quantity $quantity): void
    {
        if ($quantity->isZero()) {
            throw InvalidQuantityException::notPositive();
        }

        if ($commodity !== $this->commodity) {
            throw new CommodityMismatchException($this->code, $this->commodity, $commodity);
        }
    }
}
