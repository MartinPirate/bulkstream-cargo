<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Domain;

/**
 * Restores chronological order to movements that arrived out of sequence.
 *
 * Ordering is by occurrence time, then by a deterministic tie-breaker so two
 * events stamped with the same second always replay in the same order.
 * Within a tie, inbound flows go before outbound ones: product has to be in
 * the tank before a truck can load it.
 */
final class MovementSequencer
{
    /**
     * @param  iterable<CargoMovement>  $movements
     * @return list<CargoMovement>
     */
    public function sequence(iterable $movements): array
    {
        $ordered = is_array($movements) ? array_values($movements) : iterator_to_array($movements, false);

        usort($ordered, static function (CargoMovement $a, CargoMovement $b): int {
            return [$a->occurredAt->getTimestamp(), (int) $a->occurredAt->format('u'), self::directionRank($a), (string) $a->id]
                <=> [$b->occurredAt->getTimestamp(), (int) $b->occurredAt->format('u'), self::directionRank($b), (string) $b->id];
        });

        return $ordered;
    }

    private static function directionRank(CargoMovement $movement): int
    {
        return $movement->direction->value === 'inbound' ? 0 : 1;
    }
}
