<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Domain;

use InvalidArgumentException;

/**
 * Standard transit mass loss applied to every inbound vessel flow.
 *
 * The rate is expressed in basis points (50 bp = 0.5%) so the arithmetic stays
 * in integers. Loss is rounded half-up to the nearest kilogram and the net
 * quantity is derived as gross − loss, so net + loss always equals gross exactly.
 */
final class TransitLossPolicy
{
    public const DEFAULT_BASIS_POINTS = 50;

    public function __construct(public readonly int $basisPoints = self::DEFAULT_BASIS_POINTS)
    {
        if ($basisPoints < 0 || $basisPoints > 10_000) {
            throw new InvalidArgumentException('Transit loss must be between 0 and 10,000 basis points.');
        }
    }

    public function lossFor(Quantity $gross): Quantity
    {
        // Integer half-up rounding: (gross * bp + 5000) / 10000
        return Quantity::kg(intdiv($gross->kilograms * $this->basisPoints + 5_000, 10_000));
    }

    public function netOf(Quantity $gross): Quantity
    {
        return $gross->minus($this->lossFor($gross));
    }

    public function percentage(): float
    {
        return $this->basisPoints / 100;
    }
}
