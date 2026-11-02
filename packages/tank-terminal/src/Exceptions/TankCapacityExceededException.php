<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Exceptions;

use MartinKiragu\TankTerminal\Domain\Quantity;

final class TankCapacityExceededException extends TerminalException
{
    public function __construct(
        public readonly string $tankCode,
        public readonly Quantity $capacity,
        public readonly Quantity $currentBalance,
        public readonly Quantity $attempted,
    ) {
        parent::__construct(sprintf(
            'Tank %s overflow blocked: %s requested but only %s of %s capacity is free.',
            $tankCode,
            $attempted->format(),
            $capacity->minus($currentBalance)->format(),
            $capacity->format(),
        ));
    }
}
