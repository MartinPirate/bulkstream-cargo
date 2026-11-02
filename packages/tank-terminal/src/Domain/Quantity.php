<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Domain;

use MartinKiragu\TankTerminal\Exceptions\InvalidQuantityException;

/**
 * Immutable mass quantity stored as whole kilograms.
 *
 * Integer kilograms avoid floating-point drift when thousands of movements are
 * summed into a tank balance; the UI renders metric tonnes (1 MT = 1,000 kg).
 */
final class Quantity
{
    private function __construct(public readonly int $kilograms)
    {
        if ($kilograms < 0) {
            throw InvalidQuantityException::negative($kilograms);
        }
    }

    public static function kg(int $kilograms): self
    {
        return new self($kilograms);
    }

    public static function tonnes(float|int|string $tonnes): self
    {
        return new self((int) round(((float) $tonnes) * 1000));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->kilograms + $other->kilograms);
    }

    public function minus(self $other): self
    {
        return new self($this->kilograms - $other->kilograms);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->kilograms > $other->kilograms;
    }

    public function isZero(): bool
    {
        return $this->kilograms === 0;
    }

    public function toTonnes(): float
    {
        return $this->kilograms / 1000;
    }

    public function format(): string
    {
        return number_format($this->toTonnes(), 3).' MT';
    }

    public function equals(self $other): bool
    {
        return $this->kilograms === $other->kilograms;
    }
}
