<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Exceptions;

use MartinKiragu\TankTerminal\Enums\Commodity;

final class CommodityMismatchException extends TerminalException
{
    public function __construct(
        public readonly string $tankCode,
        public readonly Commodity $tankCommodity,
        public readonly Commodity $cargoCommodity,
    ) {
        parent::__construct(sprintf(
            'Tank %s is dedicated to %s and cannot handle %s (cross-contamination).',
            $tankCode,
            $tankCommodity->shortLabel(),
            $cargoCommodity->shortLabel(),
        ));
    }
}
