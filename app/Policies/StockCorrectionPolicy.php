<?php

namespace App\Policies;

use App\Models\StockCorrection;
use App\Models\User;

class StockCorrectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessPanel(filament()->getCurrentOrDefaultPanel());
    }

    public function view(User $user, StockCorrection $correction): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** Corrections are immutable once requested; they are decided, not edited. */
    public function update(User $user, StockCorrection $correction): bool
    {
        return false;
    }

    public function approve(User $user, StockCorrection $correction): bool
    {
        return $correction->isAwaitingApproval()
            && $user->canApprove()
            && $correction->requested_by !== $user->id;
    }

    public function delete(User $user, StockCorrection $correction): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
