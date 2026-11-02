<?php

namespace Tests\Unit\TankTerminal;

use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Domain\TankState;
use MartinKiragu\TankTerminal\Enums\Commodity;
use MartinKiragu\TankTerminal\Exceptions\CommodityMismatchException;
use MartinKiragu\TankTerminal\Exceptions\InsufficientTankStockException;
use MartinKiragu\TankTerminal\Exceptions\InvalidQuantityException;
use MartinKiragu\TankTerminal\Exceptions\TankCapacityExceededException;
use MartinKiragu\TankTerminal\Exceptions\TerminalException;
use PHPUnit\Framework\TestCase;

class TankStateTest extends TestCase
{
    private function tank(int $capacityT = 1_000, int $balanceT = 0, Commodity $commodity = Commodity::CPO): TankState
    {
        return new TankState(1, 'T-101', $commodity, Quantity::tonnes($capacityT), Quantity::tonnes($balanceT));
    }

    public function test_receives_up_to_exact_capacity(): void
    {
        $tank = $this->tank(1_000, 400);
        $tank->receive(Commodity::CPO, Quantity::tonnes(600));

        $this->assertSame(1_000_000, $tank->balance()->kilograms);
        $this->assertTrue($tank->freeCapacity()->isZero());
    }

    public function test_overflow_throws_custom_domain_exception_and_leaves_balance_untouched(): void
    {
        $tank = $this->tank(1_000, 400);

        try {
            $tank->receive(Commodity::CPO, Quantity::kg(600_001));
            $this->fail('Expected overflow to be blocked.');
        } catch (TankCapacityExceededException $e) {
            $this->assertInstanceOf(TerminalException::class, $e);
            $this->assertSame('T-101', $e->tankCode);
            $this->assertStringContainsString('overflow blocked', $e->getMessage());
        }

        $this->assertSame(400_000, $tank->balance()->kilograms);
    }

    public function test_release_cannot_exceed_stock(): void
    {
        $tank = $this->tank(1_000, 30);

        $this->expectException(InsufficientTankStockException::class);
        $tank->release(Commodity::CPO, Quantity::tonnes(31));
    }

    public function test_commodity_must_match_the_tank(): void
    {
        $tank = $this->tank(commodity: Commodity::CPOL);

        $this->expectException(CommodityMismatchException::class);
        $tank->receive(Commodity::PFAD, Quantity::tonnes(1));
    }

    public function test_zero_quantity_movements_are_rejected(): void
    {
        $this->expectException(InvalidQuantityException::class);
        $this->tank()->receive(Commodity::CPO, Quantity::zero());
    }
}
