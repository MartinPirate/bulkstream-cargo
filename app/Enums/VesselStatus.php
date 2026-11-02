<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VesselStatus: string implements HasColor, HasLabel
{
    case Expected = 'expected';
    case Berthed = 'berthed';
    case Discharging = 'discharging';
    case Departed = 'departed';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Expected => 'gray',
            self::Berthed => 'info',
            self::Discharging => 'warning',
            self::Departed => 'success',
        };
    }

    public function acceptsDischarge(): bool
    {
        return in_array($this, [self::Berthed, self::Discharging], true);
    }
}
