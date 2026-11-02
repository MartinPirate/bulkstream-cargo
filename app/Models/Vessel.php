<?php

namespace App\Models;

use App\Enums\VesselStatus;
use Database\Factories\VesselFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Vessel extends Model
{
    /** @use HasFactory<VesselFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = ['name', 'imo_number', 'voyage_number', 'status', 'eta', 'berthed_at', 'departed_at'];

    protected function casts(): array
    {
        return [
            'status' => VesselStatus::class,
            'eta' => 'datetime',
            'berthed_at' => 'datetime',
            'departed_at' => 'datetime',
        ];
    }

    public function discharges(): HasMany
    {
        return $this->hasMany(CargoMovement::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('setup')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
