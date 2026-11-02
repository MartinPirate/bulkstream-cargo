<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Domain;

use DateTimeImmutable;
use MartinKiragu\TankTerminal\Enums\Commodity;
use MartinKiragu\TankTerminal\Enums\MovementDirection;

/**
 * One cargo event from the source pipeline (meter, weighbridge, ship's log...).
 *
 * `quantity` is the gross figure as reported by the source. For inbound vessel
 * flows the engine derives the net figure via the TransitLossPolicy.
 */
final class CargoMovement
{
    public function __construct(
        public readonly int|string $id,
        public readonly MovementDirection $direction,
        public readonly int|string $tankId,
        public readonly Commodity $commodity,
        public readonly Quantity $quantity,
        public readonly DateTimeImmutable $occurredAt,
    ) {}
}
