<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Exceptions;

final class InvalidQuantityException extends TerminalException
{
    public static function negative(int $kilograms): self
    {
        return new self(sprintf('Quantities cannot be negative (got %d kg).', $kilograms));
    }

    public static function notPositive(): self
    {
        return new self('A cargo movement must move a positive quantity.');
    }
}
