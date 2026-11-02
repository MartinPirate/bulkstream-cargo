<?php

namespace App\Terminal;

use App\Enums\MovementStatus;
use App\Jobs\ProcessCargoPipeline;
use App\Models\CargoMovement;
use App\Models\User;
use App\Settings\TerminalSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use MartinKiragu\TankTerminal\Contracts\MovementLedger;
use MartinKiragu\TankTerminal\Contracts\TankRepository;
use MartinKiragu\TankTerminal\Contracts\TransactionManager;
use MartinKiragu\TankTerminal\Domain\ProcessingReport;
use MartinKiragu\TankTerminal\Domain\TransitLossPolicy;
use MartinKiragu\TankTerminal\TerminalEngine;

/**
 * Application service for the vessel → tank → truck pipeline.
 *
 * Movements are written as `pending` (or `awaiting_approval` for large
 * releases) and applied later in chronological order by the engine.
 */
class CargoPipeline
{
    /** Upper bound per run so one huge backlog cannot hold locks for minutes. */
    public const BATCH_SIZE = 500;

    public function __construct(
        private readonly TankRepository $tanks,
        private readonly MovementLedger $ledger,
        private readonly TransactionManager $transactions,
        private readonly TerminalSettings $settings,
    ) {}

    /**
     * Apply every pending movement, oldest event first.
     */
    public function processPending(): ProcessingReport
    {
        $movements = CargoMovement::query()
            ->where('status', MovementStatus::Pending)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(self::BATCH_SIZE)
            ->get()
            ->map(fn (CargoMovement $movement) => $movement->toDomain());

        return $this->engine()->process($movements);
    }

    public function approve(CargoMovement $movement, User $approver, ?string $note = null): void
    {
        $this->decide($movement, $approver, MovementStatus::Pending, $note);

        ProcessCargoPipeline::dispatch();
    }

    public function decline(CargoMovement $movement, User $approver, string $note): void
    {
        $this->decide($movement, $approver, MovementStatus::Declined, $note);
    }

    private function decide(CargoMovement $movement, User $approver, MovementStatus $outcome, ?string $note): void
    {
        if (! $approver->canApprove()) {
            throw new AuthorizationException('Only supervisors can decide on releases.');
        }

        DB::transaction(function () use ($movement, $approver, $outcome, $note): void {
            $locked = CargoMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== MovementStatus::AwaitingApproval) {
                throw ValidationException::withMessages(['status' => 'This release has already been decided.']);
            }

            // Maker-checker: the person who logged a release cannot approve it.
            if ($locked->created_by !== null && $locked->created_by === $approver->id) {
                throw new AuthorizationException('You cannot approve a release you recorded yourself.');
            }

            DB::table('cargo_movements')->where('id', $locked->id)->update([
                'status' => $outcome->value,
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'decision_note' => $note,
                'updated_at' => now(),
            ]);

            activity('approvals')
                ->performedOn($locked)
                ->causedBy($approver)
                ->withProperties(['outcome' => $outcome->value, 'note' => $note])
                ->log($outcome === MovementStatus::Pending ? 'release approved' : 'release declined');
        });
    }

    private function engine(): TerminalEngine
    {
        // Built per run so a settings change applies to the very next batch.
        return new TerminalEngine(
            tanks: $this->tanks,
            ledger: $this->ledger,
            transactions: $this->transactions,
            transitLoss: new TransitLossPolicy($this->settings->transitLossBasisPoints()),
        );
    }
}
