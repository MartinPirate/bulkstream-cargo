<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Key/value row behind App\Settings\TerminalSettings. Read through that class,
 * never directly, so defaults and caching live in one place.
 */
class TerminalSetting extends Model
{
    use LogsActivity;

    protected $fillable = ['key', 'value', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('settings')
            ->logOnly(['key', 'value'])
            ->logOnlyDirty();
    }
}
