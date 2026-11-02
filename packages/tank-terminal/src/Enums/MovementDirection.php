<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Enums;

enum MovementDirection: string
{
    /** Vessel discharge into a shore tank. */
    case Inbound = 'inbound';

    /** Truck loading out of a shore tank. */
    case Outbound = 'outbound';

    public function label(): string
    {
        return match ($this) {
            self::Inbound => 'Vessel discharge (inbound)',
            self::Outbound => 'Truck delivery (outbound)',
        };
    }
}
