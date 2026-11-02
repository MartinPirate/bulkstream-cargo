<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CorrectionStatus: string implements HasColor, HasLabel
{
    case AwaitingApproval = 'awaiting_approval';
    case Applied = 'applied';
    case Declined = 'declined';
    /** Approved, but a domain rule refused it at apply time (e.g. would overflow). */
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::AwaitingApproval => 'Awaiting approval',
            default => ucfirst($this->value),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::AwaitingApproval => 'info',
            self::Applied => 'success',
            self::Declined => 'gray',
            self::Failed => 'danger',
        };
    }
}
