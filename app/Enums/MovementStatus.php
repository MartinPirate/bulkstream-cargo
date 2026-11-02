<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MovementStatus: string implements HasColor, HasLabel
{
    /** Large release waiting for a supervisor before it may enter the pipeline. */
    case AwaitingApproval = 'awaiting_approval';

    /** Supervisor refused the release; it never touches a balance. */
    case Declined = 'declined';

    /** Received from the pipeline, waiting to be sequenced and applied. */
    case Pending = 'pending';

    /** Applied to the tank balance. */
    case Applied = 'applied';

    /** Refused by a domain rule (overflow, insufficient stock, wrong product). */
    case Rejected = 'rejected';

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
            self::Declined => 'gray',
            self::Pending => 'warning',
            self::Applied => 'success',
            self::Rejected => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Applied, self::Rejected, self::Declined], true);
    }
}
