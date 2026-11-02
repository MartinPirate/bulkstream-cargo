<?php

namespace App\Policies;

use App\Enums\MovementStatus;
use App\Models\CargoMovement;
use App\Models\User;

class CargoMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessPanel(filament()->getCurrentOrDefaultPanel());
    }

    public function view(User $user, CargoMovement $movement): bool
    {
        return $this->viewAny($user);
    }

    /** Every terminal role records movements. */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** Only a release still waiting for approval may be corrected, by its author or an admin. */
    public function update(User $user, CargoMovement $movement): bool
    {
        return $movement->status === MovementStatus::AwaitingApproval
            && ($movement->created_by === $user->id || $user->isAdmin());
    }

    /** Supervisors decide; never on their own entries (maker-checker). */
    public function approve(User $user, CargoMovement $movement): bool
    {
        return $movement->status === MovementStatus::AwaitingApproval
            && $user->canApprove()
            && $movement->created_by !== $user->id;
    }

    public function delete(User $user, CargoMovement $movement): bool
    {
        return $user->isAdmin()
            && in_array($movement->status, [MovementStatus::AwaitingApproval, MovementStatus::Declined], true);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
