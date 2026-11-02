<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Terminal roles. Stored via spatie/laravel-permission; this enum is the single
 * source of truth for role names so they are never typed as loose strings.
 */
enum UserRole: string implements HasColor, HasLabel
{
    /** Manages tanks, vessels, users and terminal settings. */
    case Admin = 'admin';

    /** Approves large releases and stock corrections. */
    case Supervisor = 'supervisor';

    /** Records discharges and truck loadings, requests corrections. */
    case Operator = 'operator';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Supervisor => 'warning',
            self::Operator => 'info',
        };
    }
}
