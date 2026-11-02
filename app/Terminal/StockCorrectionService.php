<?php

namespace App\Terminal;

use App\Enums\CorrectionStatus;
use App\Models\StockCorrection;
use App\Models\Tank;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use MartinKiragu\TankTerminal\Contracts\TankRepository;
use MartinKiragu\TankTerminal\Contracts\TransactionManager;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Exceptions\TerminalException;

/**
 * Maker-checker stock corrections.
 *
 * A correction never bypasses the domain: it goes through the same locked
 * TankState as cargo movements, so it cannot overflow a tank or drive the
 * balance negative. No transit loss applies; it is a book adjustment.
 */
class StockCorrectionService
{
    public function __construct(
        private readonly TankRepository $tanks,
        private readonly TransactionManager $transactions,
    ) {}

    public function request(Tank $tank, string $direction, int $quantityKg, string $reason, User $requester): StockCorrection
    {
        if (! in_array($direction, ['increase', 'decrease'], true)) {
            throw ValidationException::withMessages(['direction' => 'Direction must be increase or decrease.']);
        }

        if ($quantityKg <= 0) {
            throw ValidationException::withMessages(['quantity_kg' => 'Quantity must be positive.']);
        }

        $correction = new StockCorrection([
            'tank_id' => $tank->id,
            'direction' => $direction,
            'quantity_kg' => $quantityKg,
            'reason' => $reason,
        ]);
        $correction->status = CorrectionStatus::AwaitingApproval;
        $correction->requested_by = $requester->id;
        $correction->save();

        return $correction;
    }

    public function approve(StockCorrection $correction, User $approver, ?string $note = null): StockCorrection
    {
        $this->guardApprover($correction, $approver);

        try {
            $this->transactions->run(function () use ($correction, $approver, $note): void {
                $locked = $this->lockAwaiting($correction);

                $tank = $this->tanks->lockForUpdate($locked->tank_id);
                $quantity = Quantity::kg($locked->quantity_kg);

                $locked->direction === 'increase'
                    ? $tank->receive($tank->commodity, $quantity)
                    : $tank->release($tank->commodity, $quantity);

                $this->tanks->persist($tank);

                $locked->status = CorrectionStatus::Applied;
                $locked->decided_by = $approver->id;
                $locked->decided_at = now();
                $locked->decision_note = $note;
                $locked->applied_at = now();
                $locked->balance_after_kg = $tank->balance()->kilograms;
                $locked->save();
            });
        } catch (TerminalException $rule) {
            // Rolled back; record that the approved correction could not be applied.
            $this->transactions->run(function () use ($correction, $approver, $rule): void {
                $locked = $this->lockAwaiting($correction);
                $locked->status = CorrectionStatus::Failed;
                $locked->decided_by = $approver->id;
                $locked->decided_at = now();
                $locked->decision_note = $rule->getMessage();
                $locked->save();
            });
        }

        return $correction->refresh();
    }

    public function decline(StockCorrection $correction, User $approver, string $note): StockCorrection
    {
        $this->guardApprover($correction, $approver);

        $this->transactions->run(function () use ($correction, $approver, $note): void {
            $locked = $this->lockAwaiting($correction);
            $locked->status = CorrectionStatus::Declined;
            $locked->decided_by = $approver->id;
            $locked->decided_at = now();
            $locked->decision_note = $note;
            $locked->save();
        });

        return $correction->refresh();
    }

    private function guardApprover(StockCorrection $correction, User $approver): void
    {
        if (! $approver->canApprove()) {
            throw new AuthorizationException('Only supervisors can decide on stock corrections.');
        }

        if ($correction->requested_by === $approver->id) {
            throw new AuthorizationException('You cannot approve a correction you requested.');
        }
    }

    private function lockAwaiting(StockCorrection $correction): StockCorrection
    {
        $locked = StockCorrection::query()->whereKey($correction->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== CorrectionStatus::AwaitingApproval) {
            throw ValidationException::withMessages(['status' => 'This correction has already been decided.']);
        }

        return $locked;
    }
}
