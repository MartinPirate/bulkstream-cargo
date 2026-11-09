<?php

namespace App\Policies;

use App\Models\CommodityProfile;
use App\Models\User;

/**
 * Everyone on the terminal can read handling profiles; only admins tune them.
 * Profiles are never created or deleted: the three codes are domain constants.
 */
class CommodityProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessPanel(filament()->getCurrentOrDefaultPanel());
    }

    public function view(User $user, CommodityProfile $profile): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CommodityProfile $profile): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CommodityProfile $profile): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
