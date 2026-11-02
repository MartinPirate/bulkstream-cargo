<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vessel;

class VesselPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessPanel(filament()->getCurrentOrDefaultPanel());
    }

    public function view(User $user, Vessel $vessel): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->canApprove();
    }

    public function update(User $user, Vessel $vessel): bool
    {
        return $user->canApprove();
    }

    public function delete(User $user, Vessel $vessel): bool
    {
        return $user->isAdmin() && ! $vessel->discharges()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
