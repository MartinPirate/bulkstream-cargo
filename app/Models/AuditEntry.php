<?php

namespace App\Models;

use LogicException;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only view over the activity log for the Audit screen.
 * Audit rows are never edited or deleted from the application.
 */
class AuditEntry extends Activity
{
    protected $table = 'activity_log';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit entries are immutable.'));
    }
}
