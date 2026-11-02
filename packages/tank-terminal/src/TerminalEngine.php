<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal;

use MartinKiragu\TankTerminal\Contracts\MovementLedger;
use MartinKiragu\TankTerminal\Contracts\TankRepository;
use MartinKiragu\TankTerminal\Contracts\TransactionManager;
use MartinKiragu\TankTerminal\Domain\CargoMovement;
use MartinKiragu\TankTerminal\Domain\MovementSequencer;
use MartinKiragu\TankTerminal\Domain\ProcessingReport;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Domain\TransitLossPolicy;
use MartinKiragu\TankTerminal\Enums\MovementDirection;
use MartinKiragu\TankTerminal\Exceptions\TerminalException;

/**
 * Applies cargo movements to tank balances.
 *
 * 1. Sequence: movements are re-sorted chronologically before any balance changes.
 * 2. Isolate: each movement runs in its own transaction holding a row lock on the
 *    movement record and on the tank, so concurrent workers cannot both claim the
 *    same free capacity, and a movement is never applied twice.
 * 3. Adjust: inbound vessel flows are reduced by the transit loss policy.
 * 4. Guard: the TankState aggregate throws a domain exception on overflow,
 *    insufficient stock or commodity mismatch; the transaction rolls back and
 *    the rejection is recorded in a separate transaction.
 */
final class TerminalEngine
{
    public function __construct(
        private readonly TankRepository $tanks,
        private readonly MovementLedger $ledger,
        private readonly TransactionManager $transactions,
        private readonly TransitLossPolicy $transitLoss,
        private readonly MovementSequencer $sequencer = new MovementSequencer,
    ) {}

    /**
     * @param  iterable<CargoMovement>  $movements
     */
    public function process(iterable $movements): ProcessingReport
    {
        $report = new ProcessingReport;

        foreach ($this->sequencer->sequence($movements) as $movement) {
            $report->processingOrder[] = $movement->id;

            try {
                $applied = $this->transactions->run(fn (): bool => $this->apply($movement));

                if ($applied) {
                    $report->applied[] = $movement->id;
                } else {
                    $report->skipped[] = $movement->id;
                }
            } catch (TerminalException $reason) {
                // The failed attempt was rolled back; persist the rejection on its own.
                $this->transactions->run(fn () => $this->ledger->markRejected($movement, $reason));
                $report->rejected[$movement->id] = $reason->getMessage();
            }
        }

        return $report;
    }

    /**
     * Runs inside a transaction. Returns false if the movement was already handled.
     */
    private function apply(CargoMovement $movement): bool
    {
        if (! $this->ledger->claim($movement)) {
            return false;
        }

        $tank = $this->tanks->lockForUpdate($movement->tankId);

        if ($movement->direction === MovementDirection::Inbound) {
            $loss = $this->transitLoss->lossFor($movement->quantity);
            $net = $movement->quantity->minus($loss);
            $tank->receive($movement->commodity, $net);
        } else {
            $loss = Quantity::zero();
            $net = $movement->quantity;
            $tank->release($movement->commodity, $net);
        }

        $this->tanks->persist($tank);
        $this->ledger->markApplied($movement, $net, $loss, $tank->balance());

        return true;
    }
}
