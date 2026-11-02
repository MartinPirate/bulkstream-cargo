<?php

namespace Tests\Unit\TankTerminal;

use InvalidArgumentException;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Domain\TransitLossPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransitLossPolicyTest extends TestCase
{
    public function test_default_rate_is_half_a_percent(): void
    {
        $policy = new TransitLossPolicy;

        $this->assertSame(50, $policy->basisPoints);
        $this->assertSame(0.5, $policy->percentage());
    }

    #[DataProvider('discharges')]
    public function test_loss_and_net_for_inbound_flows(int $grossKg, int $expectedLossKg, int $expectedNetKg): void
    {
        $policy = new TransitLossPolicy;
        $gross = Quantity::kg($grossKg);

        $this->assertSame($expectedLossKg, $policy->lossFor($gross)->kilograms);
        $this->assertSame($expectedNetKg, $policy->netOf($gross)->kilograms);
    }

    public static function discharges(): array
    {
        return [
            '5,000 MT parcel' => [5_000_000, 25_000, 4_975_000],
            '4,200 MT parcel' => [4_200_000, 21_000, 4_179_000],
            'rounds half up' => [1_100, 6, 1_094],         // 5.5 kg -> 6
            'rounds down below half' => [1_099, 5, 1_094], // 5.495 kg -> 5
            'tiny flow' => [50, 0, 50],
        ];
    }

    public function test_net_plus_loss_always_equals_gross(): void
    {
        $policy = new TransitLossPolicy;

        foreach ([1, 999, 1_000, 123_457, 7_654_321, 99_999_999] as $kg) {
            $gross = Quantity::kg($kg);
            $this->assertSame($kg, $policy->netOf($gross)->kilograms + $policy->lossFor($gross)->kilograms, "gross {$kg} kg");
        }
    }

    public function test_rate_is_configurable_within_bounds(): void
    {
        $this->assertSame(10_000, (new TransitLossPolicy(100))->lossFor(Quantity::kg(1_000_000))->kilograms);

        $this->expectException(InvalidArgumentException::class);
        new TransitLossPolicy(-1);
    }
}
