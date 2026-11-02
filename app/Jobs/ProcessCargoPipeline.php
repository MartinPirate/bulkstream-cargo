<?php

namespace App\Jobs;

use App\Terminal\CargoPipeline;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Drains the pending queue in chronological order.
 *
 * - UniqueUntilProcessing collapses a burst of new movements into one queued run,
 *   while still allowing a fresh run to be queued as soon as this one starts.
 * - WithoutOverlapping keeps a single worker applying movements at a time, so
 *   two batches can never interleave out of time order.
 */
class ProcessCargoPipeline implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function middleware(): array
    {
        return [(new WithoutOverlapping('cargo-pipeline'))->releaseAfter(5)->expireAfter(300)];
    }

    public function handle(CargoPipeline $pipeline): void
    {
        $report = $pipeline->processPending();

        if ($report->total() > 0) {
            Log::info('Cargo pipeline run', [
                'applied' => count($report->applied),
                'rejected' => count($report->rejected),
                'skipped' => count($report->skipped),
            ]);
        }

        // A full batch means more may be waiting.
        if ($report->total() >= CargoPipeline::BATCH_SIZE) {
            self::dispatch();
        }
    }
}
