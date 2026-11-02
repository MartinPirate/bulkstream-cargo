<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Exceptions;

use MartinKiragu\TankTerminal\Domain\Quantity;

final class InsufficientTankStockException extends TerminalException
{
    public function __construct(
        public readonly string $tankCode,
        public readonly Quantity $currentBalance,
        public readonly Quantity $attempted,
    ) {
        parent::__construct(sprintf(
            'Tank %s cannot release %s: only %s in stock.',
            $tankCode,
            $attempted->format(),
            $currentBalance->format(),
        ));
    }
}
