<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Domain;

/**
 * Summary of one engine run, in the order movements were actually applied.
 */
final class ProcessingReport
{
    /** @var list<int|string> */
    public array $applied = [];

    /** @var array<int|string, string> movement id => reason */
    public array $rejected = [];

    /** @var list<int|string> movements another worker had already handled */
    public array $skipped = [];

    /** @var list<int|string> ids in the order they were processed */
    public array $processingOrder = [];

    public function total(): int
    {
        return count($this->processingOrder);
    }
}
