<?php

namespace App\Models;

use App\Observers\TankObserver;
use Database\Factories\TankFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Enums\Commodity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A shore storage tank dedicated to a single commodity.
 *
 * balance_kg is never written through this model: it only changes inside the
 * terminal engine's locked transaction (see EloquentTankRepository). The
 * TankObserver rejects any attempt to change it here.
 *
 * @property int $id
 * @property string $code
 * @property Commodity $commodity
 * @property int $capacity_kg
 * @property int $balance_kg
 */
#[ObservedBy(TankObserver::class)]
class Tank extends Model
{
    /** @use HasFactory<TankFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = ['code', 'commodity', 'capacity_kg', 'temperature_c', 'is_active'];

    protected function casts(): array
    {
        return [
            'commodity' => Commodity::class,
            'capacity_kg' => 'integer',
            'balance_kg' => 'integer',
            'temperature_c' => 'decimal:1',
            'is_active' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CargoMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function capacity(): Quantity
    {
        return Quantity::kg($this->capacity_kg);
    }

    public function balance(): Quantity
    {
        return Quantity::kg($this->balance_kg ?? 0);
    }

    public function freeCapacity(): Quantity
    {
        return Quantity::kg(max(0, $this->capacity_kg - ($this->balance_kg ?? 0)));
    }

    public function fillPercent(): float
    {
        return $this->capacity_kg > 0 ? round(($this->balance_kg / $this->capacity_kg) * 100, 1) : 0.0;
    }

    /** The editable handling profile for this tank's commodity. */
    public function profile(): CommodityProfile
    {
        return CommodityProfile::for($this->commodity);
    }

    public function isTemperatureOutOfRange(): bool
    {
        if ($this->temperature_c === null || $this->balance_kg === 0) {
            return false;
        }

        [$min, $max] = $this->profile()->temperatureRange();

        return (float) $this->temperature_c < $min || (float) $this->temperature_c > $max;
    }

    public function getActivitylogOptions(): LogOptions
    {
        // balance_kg is excluded: stock changes are audited per movement, not per row write.
        return LogOptions::defaults()->useLogName('setup')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
