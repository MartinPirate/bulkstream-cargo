<?php

namespace Tests\Unit\TankTerminal;

use DateTimeImmutable;
use MartinKiragu\TankTerminal\Contracts\MovementLedger;
use MartinKiragu\TankTerminal\Contracts\TankRepository;
use MartinKiragu\TankTerminal\Contracts\TransactionManager;
use MartinKiragu\TankTerminal\Domain\CargoMovement;
use MartinKiragu\TankTerminal\Domain\MovementSequencer;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Domain\TankState;
use MartinKiragu\TankTerminal\Domain\TransitLossPolicy;
use MartinKiragu\TankTerminal\Enums\Commodity;
use MartinKiragu\TankTerminal\Enums\MovementDirection;
use MartinKiragu\TankTerminal\Exceptions\TerminalException;
use MartinKiragu\TankTerminal\TerminalEngine;
use PHPUnit\Framework\TestCase;

/**
 * Engine behaviour with in-memory adapters: no framework, no database.
 */
class TerminalEngineTest extends TestCase
{
    private InMemoryTanks $tanks;

    private InMemoryLedger $ledger;

    protected function setUp(): void
    {
        $this->tanks = new InMemoryTanks;
        $this->ledger = new InMemoryLedger;
    }

    private function engine(): TerminalEngine
    {
        return new TerminalEngine($this->tanks, $this->ledger, new PassThroughTransactions, new TransitLossPolicy);
    }

    private function move(int $id, MovementDirection $dir, int $tonnes, string $at, int $tank = 1): CargoMovement
    {
        return new CargoMovement($id, $dir, $tank, Commodity::CPO, Quantity::tonnes($tonnes), new DateTimeImmutable($at));
    }

    public function test_out_of_order_events_are_resequenced_before_balances_change(): void
    {
        $this->tanks->add(new TankState(1, 'T-1', Commodity::CPO, Quantity::tonnes(1_000), Quantity::zero()));

        // Arrival order: truck first, then the discharge that actually happened earlier.
        $report = $this->engine()->process([
            $this->move(2, MovementDirection::Outbound, 100, '2026-11-01 10:00:00'),
            $this->move(1, MovementDirection::Inbound, 500, '2026-11-01 08:00:00'),
        ]);

        // Applied in event-time order, so the truck finds stock and is not rejected.
        $this->assertSame([1, 2], $report->processingOrder);
        $this->assertSame([1, 2], $report->applied);
        $this->assertSame([], $report->rejected);
        $this->assertSame(397_500, $this->tanks->get(1)->balance()->kilograms); // 500 - 0.5% - 100
    }

    public function test_without_sequencing_the_same_feed_would_have_wrongly_rejected_the_truck(): void
    {
        $this->tanks->add(new TankState(1, 'T-1', Commodity::CPO, Quantity::tonnes(1_000), Quantity::zero()));

        $truckFirst = [$this->move(2, MovementDirection::Outbound, 100, '2026-11-01 10:00:00')];
        $report = $this->engine()->process($truckFirst);

        $this->assertArrayHasKey(2, $report->rejected);
    }

    public function test_inbound_flows_are_reduced_by_transit_loss(): void
    {
        $this->tanks->add(new TankState(1, 'T-1', Commodity::CPO, Quantity::tonnes(10_000), Quantity::zero()));

        $this->engine()->process([$this->move(1, MovementDirection::Inbound, 5_000, '2026-11-01 08:00')]);

        $this->assertSame(4_975_000, $this->tanks->get(1)->balance()->kilograms);
        $this->assertSame(25_000, $this->ledger->applied[1]['loss']);
    }

    public function test_rejection_is_recorded_and_does_not_stop_later_movements(): void
    {
        $this->tanks->add(new TankState(1, 'T-1', Commodity::CPO, Quantity::tonnes(1_000), Quantity::tonnes(900)));

        $report = $this->engine()->process([
            $this->move(1, MovementDirection::Inbound, 500, '2026-11-01 08:00'),  // overflows
            $this->move(2, MovementDirection::Outbound, 50, '2026-11-01 09:00'),  // fine
        ]);

        $this->assertSame([2], $report->applied);
        $this->assertArrayHasKey(1, $report->rejected);
        $this->assertSame(850_000, $this->tanks->get(1)->balance()->kilograms);
    }

    public function test_already_processed_movements_are_skipped_idempotently(): void
    {
        $this->tanks->add(new TankState(1, 'T-1', Commodity::CPO, Quantity::tonnes(1_000), Quantity::zero()));
        $movement = $this->move(1, MovementDirection::Inbound, 100, '2026-11-01 08:00');

        $this->engine()->process([$movement]);
        $second = $this->engine()->process([$movement]);

        $this->assertSame([1], $second->skipped);
        $this->assertSame(99_500, $this->tanks->get(1)->balance()->kilograms);
    }

    public function test_sequencer_puts_inbound_before_outbound_on_identical_timestamps(): void
    {
        $at = '2026-11-01 08:00:00';
        $ordered = (new MovementSequencer)->sequence([
            $this->move(9, MovementDirection::Outbound, 10, $at),
            $this->move(3, MovementDirection::Inbound, 10, $at),
        ]);

        $this->assertSame([3, 9], array_map(fn ($m) => $m->id, $ordered));
    }
}

final class InMemoryTanks implements TankRepository
{
    /** @var array<int|string, TankState> */
    private array $tanks = [];

    public function add(TankState $tank): void
    {
        $this->tanks[$tank->id] = $tank;
    }

    public function get(int|string $id): TankState
    {
        return $this->tanks[$id];
    }

    public function lockForUpdate(int|string $tankId): TankState
    {
        $t = $this->tanks[$tankId];

        // Hand out a copy so a rolled-back attempt never leaks into stored state.
        return new TankState($t->id, $t->code, $t->commodity, $t->capacity, $t->balance());
    }

    public function persist(TankState $tank): void
    {
        $this->tanks[$tank->id] = $tank;
    }
}

final class InMemoryLedger implements MovementLedger
{
    /** @var array<int|string, array{net: int, loss: int}> */
    public array $applied = [];

    /** @var array<int|string, string> */
    public array $rejected = [];

    public function claim(CargoMovement $movement): bool
    {
        return ! isset($this->applied[$movement->id]) && ! isset($this->rejected[$movement->id]);
    }

    public function markApplied(CargoMovement $movement, Quantity $net, Quantity $loss, Quantity $balanceAfter): void
    {
        $this->applied[$movement->id] = ['net' => $net->kilograms, 'loss' => $loss->kilograms];
    }

    public function markRejected(CargoMovement $movement, TerminalException $reason): void
    {
        $this->rejected[$movement->id] = $reason->getMessage();
    }
}

final class PassThroughTransactions implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}
