<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use MartinKiragu\TankTerminal\Enums\Commodity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * How a commodity is handled in storage (temperature band, heating, notes).
 *
 * One row per Commodity case; rows are never created or deleted from the UI
 * because the domain rules are keyed on the three codes.
 *
 * @property int $id
 * @property Commodity $code
 * @property string $name
 * @property string $handling_note
 * @property string $storage_temp_min_c
 * @property string $storage_temp_max_c
 * @property bool $requires_heating
 */
class CommodityProfile extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'handling_note', 'storage_temp_min_c', 'storage_temp_max_c', 'requires_heating'];

    /** @var array<string, self>|null Per-request memo; cleared whenever a profile is saved. */
    private static ?array $memo = null;

    protected static function booted(): void
    {
        static::saved(fn () => self::$memo = null);
    }

    protected function casts(): array
    {
        return [
            'code' => Commodity::class,
            'storage_temp_min_c' => 'decimal:1',
            'storage_temp_max_c' => 'decimal:1',
            'requires_heating' => 'boolean',
        ];
    }

    /**
     * The profile for a commodity. Falls back to the domain defaults (unsaved)
     * if the row is missing, so a bad deploy degrades to sane values instead of
     * breaking tank alerts.
     */
    public static function for(Commodity $commodity): self
    {
        self::$memo ??= self::query()->get()->keyBy(fn (self $p) => $p->code->value)->all();

        if (! isset(self::$memo[$commodity->value])) {
            Log::warning('Commodity profile row missing; using domain defaults.', ['commodity' => $commodity->value]);
            self::$memo[$commodity->value] = self::defaultsFor($commodity);
        }

        return self::$memo[$commodity->value];
    }

    public static function flushMemo(): void
    {
        self::$memo = null;
    }

    public static function defaultsFor(Commodity $commodity): self
    {
        [$min, $max] = $commodity->storageTemperatureRange();

        $profile = new self([
            'name' => $commodity->label(),
            'handling_note' => $commodity->handlingNote(),
            'storage_temp_min_c' => $min,
            'storage_temp_max_c' => $max,
            'requires_heating' => $commodity->requiresHeating(),
        ]);
        $profile->code = $commodity;

        return $profile;
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function temperatureRange(): array
    {
        return [(float) $this->storage_temp_min_c, (float) $this->storage_temp_max_c];
    }

    public function temperatureBandLabel(): string
    {
        [$min, $max] = $this->temperatureRange();
        $fmt = fn (float $v): string => rtrim(rtrim(number_format($v, 1), '0'), '.');

        return $fmt($min).'–'.$fmt($max).' °C';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('setup')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
