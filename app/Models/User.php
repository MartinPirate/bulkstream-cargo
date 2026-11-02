<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Only users holding a terminal role may sign in to the control panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(array_map(fn (UserRole $role) => $role->value, UserRole::cases()));
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin->value);
    }

    /**
     * Supervisors and admins can approve; operators cannot.
     */
    public function canApprove(): bool
    {
        return $this->hasAnyRole([UserRole::Admin->value, UserRole::Supervisor->value]);
    }

    public function primaryRole(): ?UserRole
    {
        foreach (UserRole::cases() as $role) {
            if ($this->hasRole($role->value)) {
                return $role;
            }
        }

        return null;
    }
}
