<?php

namespace App\Filament\Support;

use App\Models\CommodityProfile;
use App\Models\Tank;
use MartinKiragu\TankTerminal\Enums\Commodity;

/**
 * HTML option labels for the searchable (select2-style) dropdowns.
 *
 * Every dynamic value goes through e(): tank codes and profile names are
 * admin-editable, and these strings are rendered with Select::allowHtml().
 */
final class RichOptions
{
    /** Filament colour name per commodity, shared by badges across the panel. */
    public static function commodityColor(Commodity $commodity): string
    {
        return match ($commodity) {
            Commodity::CPO => 'warning',
            Commodity::CPOL => 'success',
            Commodity::PFAD => 'gray',
        };
    }

    /**
     * @return array<string, string> commodity value => HTML label
     */
    public static function commodities(): array
    {
        $options = [];
        foreach (Commodity::cases() as $commodity) {
            $profile = CommodityProfile::for($commodity);
            $heat = $profile->requires_heating ? 'Heated' : 'Ambient';

            $options[$commodity->value] = sprintf(
                '<span class="bs-opt"><span class="bs-opt__row">%s<span class="bs-opt__title">%s</span></span>'
                .'<span class="bs-opt__meta">%s · %s · %s</span></span>',
                self::chip($commodity),
                e($profile->name),
                e($heat),
                e($profile->temperatureBandLabel()),
                e($profile->handling_note),
            );
        }

        return $options;
    }

    /**
     * @param  iterable<Tank>  $tanks
     * @return array<int, string> tank id => HTML label
     */
    public static function tanks(iterable $tanks): array
    {
        $options = [];
        foreach ($tanks as $tank) {
            $fill = min(100, max(0, $tank->fillPercent()));
            $level = $fill >= 90 ? 'high' : ($fill <= 10 ? 'low' : 'ok');

            $options[$tank->id] = sprintf(
                '<span class="bs-opt"><span class="bs-opt__row">%s<span class="bs-opt__title">%s</span>'
                .'<span class="bs-opt__end">%s%%</span></span>'
                .'<span class="bs-meter bs-meter--%s"><span style="width:%s%%"></span></span>'
                .'<span class="bs-opt__meta">%s in tank · %s free of %s</span></span>',
                self::chip($tank->commodity),
                e($tank->code),
                e(number_format($fill, 1)),
                $level,
                e((string) $fill),
                e($tank->balance()->format()),
                e($tank->freeCapacity()->format()),
                e($tank->capacity()->format()),
            );
        }

        return $options;
    }

    private static function chip(Commodity $commodity): string
    {
        return sprintf(
            '<span class="bs-chip bs-chip--%s">%s</span>',
            e($commodity->value),
            e($commodity->shortLabel()),
        );
    }
}
