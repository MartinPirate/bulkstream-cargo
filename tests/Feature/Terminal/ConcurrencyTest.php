<?php

namespace Tests\Feature\Terminal;

use App\Enums\MovementStatus;
use App\Models\CargoMovement;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Real race conditions: many OS processes, each with its own MySQL connection,
 * apply movements against the same tank at the same instant. Uses committed
 * data (DatabaseMigrations, not RefreshDatabase) so child processes can see it.
 */
class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;
    use TerminalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_parallel_discharges_never_overfill_a_tank(): void
    {
        $tank = $this->makeTank(1_000);
        $vessel = $this->makeVessel();

        // 20 parcels of 100 MT gross (99.5 MT net): only 10 can physically fit.
        $ids = collect(range(1, 20))
            ->map(fn (int $i) => $this->discharge($tank, $vessel, 100, now()->subMinutes(30)->addSeconds($i), "RACE-{$i}")->id);

        $results = $this->runInParallel($ids->all());

        $this->assertSame(995_000, $tank->fresh()->balance_kg);
        $this->assertLessThanOrEqual($tank->capacity_kg, $tank->fresh()->balance_kg);
        $this->assertSame(10, CargoMovement::query()->where('status', MovementStatus::Applied)->count());
        $this->assertSame(10, CargoMovement::query()->where('status', MovementStatus::Rejected)->count());
        $this->assertSame(10, collect($results)->where('applied', true)->count());
    }

    public function test_the_same_movement_delivered_to_many_workers_is_applied_exactly_once(): void
    {
        $tank = $this->makeTank(1_000);
        $movement = $this->discharge($tank, $this->makeVessel(), 100, now(), 'DUP-1');

        $results = $this->runInParallel(array_fill(0, 8, $movement->id));

        $this->assertSame(1, collect($results)->where('applied', true)->count());
        $this->assertSame(7, collect($results)->where('skipped', true)->count());
        $this->assertSame(99_500, $tank->fresh()->balance_kg);
    }

    /**
     * @param  list<int>  $movementIds
     * @return list<array{id: int, applied: bool, rejected: bool, skipped: bool}>
     */
    private function runInParallel(array $movementIds): array
    {
        $env = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => config('database.default'),
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'QUEUE_CONNECTION' => 'sync',
            'CACHE_STORE' => 'array',
        ];

        $processes = array_map(
            fn (int $id) => new Process([PHP_BINARY, base_path('artisan'), 'terminal:apply-movement', (string) $id], base_path(), $env, null, 60),
            $movementIds,
        );

        foreach ($processes as $process) {
            $process->start();
        }

        return array_map(function (Process $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

            return json_decode(trim(last(explode("\n", trim($process->getOutput())))), true);
        }, $processes);
    }
}
