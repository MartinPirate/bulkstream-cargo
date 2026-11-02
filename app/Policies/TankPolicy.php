<?php

namespace App\Policies;

use App\Models\Tank;
use App\Models\User;

class TankPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessPanel(filament()->getCurrentOrDefaultPanel());
    }

    public function view(User $user, Tank $tank): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Tank $tank): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Tank $tank): bool
    {
        return $user->isAdmin() && $tank->balance_kg === 0;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
