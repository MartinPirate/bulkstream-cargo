<?php

namespace App\Models;

use App\Enums\CorrectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Maker-checker stock correction. Created by an operator, decided by a
 * supervisor, applied by StockCorrectionService under a tank row lock.
 */
class StockCorrection extends Model
{
    use LogsActivity;

    protected $fillable = ['tank_id', 'direction', 'quantity_kg', 'reason'];

    protected function casts(): array
    {
        return [
            'status' => CorrectionStatus::class,
            'quantity_kg' => 'integer',
            'balance_after_kg' => 'integer',
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === CorrectionStatus::AwaitingApproval;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('corrections')
            ->logOnly(['tank_id', 'direction', 'quantity_kg', 'reason', 'status', 'decided_by', 'decision_note'])
            ->logOnlyDirty();
    }
}
