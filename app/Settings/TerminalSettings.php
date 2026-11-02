<?php

namespace App\Settings;

use App\Models\TerminalSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Typed access to operator-tunable terminal settings.
 *
 * Every setting has a declared default and bounds, so a missing or corrupt row
 * can never push the engine into an invalid state. The cache is cleared only
 * after the write transaction commits.
 */
class TerminalSettings
{
    private const CACHE_KEY = 'terminal-settings:v1';

    /**
     * key => [default, min, max]
     *
     * @var array<string, array{0: int, 1: int, 2: int}>
     */
    public const DEFINITIONS = [
        // Standard transit mass loss on inbound vessel flows, in basis points (50 = 0.5%).
        'transit_loss_basis_points' => [50, 0, 500],
        // Outbound releases at or above this many kg need supervisor approval.
        'release_approval_threshold_kg' => [100_000, 1_000, 100_000_000],
        // Dashboard warns when a tank is filled beyond this percentage.
        'high_level_alarm_percent' => [90, 50, 100],
        // Dashboard warns when a tank drops below this percentage.
        'low_level_alarm_percent' => [10, 0, 50],
    ];

    public function transitLossBasisPoints(): int
    {
        return $this->get('transit_loss_basis_points');
    }

    public function releaseApprovalThresholdKg(): int
    {
        return $this->get('release_approval_threshold_kg');
    }

    public function highLevelAlarmPercent(): int
    {
        return $this->get('high_level_alarm_percent');
    }

    public function lowLevelAlarmPercent(): int
    {
        return $this->get('low_level_alarm_percent');
    }

    /**
     * @return array<string, int>
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => TerminalSetting::query()->pluck('value', 'key')->all());

        $values = [];
        foreach (self::DEFINITIONS as $key => [$default, $min, $max]) {
            $raw = $stored[$key] ?? $default;
            $values[$key] = is_numeric($raw) ? max($min, min($max, (int) $raw)) : $default;
        }

        return $values;
    }

    public function get(string $key): int
    {
        if (! array_key_exists($key, self::DEFINITIONS)) {
            throw new InvalidArgumentException("Unknown terminal setting [{$key}].");
        }

        return $this->all()[$key];
    }

    /**
     * @param  array<string, int|string>  $values
     */
    public function update(array $values, ?int $userId = null): void
    {
        DB::transaction(function () use ($values, $userId): void {
            foreach ($values as $key => $value) {
                if (! array_key_exists($key, self::DEFINITIONS)) {
                    throw new InvalidArgumentException("Unknown terminal setting [{$key}].");
                }

                [, $min, $max] = self::DEFINITIONS[$key];
                if (! is_numeric($value) || (int) $value < $min || (int) $value > $max) {
                    throw new InvalidArgumentException("Setting [{$key}] must be between {$min} and {$max}.");
                }

                // Model save (not a query update) so the change lands in the audit log.
                $setting = TerminalSetting::query()->firstOrNew(['key' => $key]);
                $setting->value = (int) $value;
                $setting->updated_by = $userId;
                $setting->save();
            }

            DB::afterCommit(fn () => Cache::forget(self::CACHE_KEY));
        });
    }
}
