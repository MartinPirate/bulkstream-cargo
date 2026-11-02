<?php

use App\Jobs\ProcessCargoPipeline;
use App\Models\CargoMovement;
use App\Settings\TerminalSettings;
use App\Terminal\CargoPipeline;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use MartinKiragu\TankTerminal\Contracts\MovementLedger;
use MartinKiragu\TankTerminal\Contracts\TankRepository;
use MartinKiragu\TankTerminal\Contracts\TransactionManager;
use MartinKiragu\TankTerminal\Domain\TransitLossPolicy;
use MartinKiragu\TankTerminal\TerminalEngine;

// Backstop: if a dispatch was ever lost, pending movements are still applied within a minute.
Schedule::job(new ProcessCargoPipeline)->everyMinute()->withoutOverlapping();

Artisan::command('terminal:process', function () {
    $report = app(CargoPipeline::class)->processPending();
    $this->info(sprintf('Applied %d, rejected %d, skipped %d.', count($report->applied), count($report->rejected), count($report->skipped)));
    foreach ($report->rejected as $id => $reason) {
        $this->warn("  #{$id}: {$reason}");
    }
})->purpose('Apply all pending cargo movements in chronological order');

/*
 * Applies exactly one movement. Used by the concurrency test to launch many
 * OS processes that race for the same tank at the same moment.
 */
Artisan::command('terminal:apply-movement {id}', function (int $id) {
    $movement = CargoMovement::query()->findOrFail($id);

    $engine = new TerminalEngine(
        tanks: app(TankRepository::class),
        ledger: app(MovementLedger::class),
        transactions: app(TransactionManager::class),
        transitLoss: new TransitLossPolicy(app(TerminalSettings::class)->transitLossBasisPoints()),
    );

    $report = $engine->process([$movement->toDomain()]);
    $this->line(json_encode([
        'id' => $id,
        'applied' => $report->applied !== [],
        'rejected' => $report->rejected !== [],
        'skipped' => $report->skipped !== [],
    ]));
})->purpose('Apply a single cargo movement (concurrency testing)');
