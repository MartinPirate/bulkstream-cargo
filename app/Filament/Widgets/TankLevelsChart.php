<?php

namespace App\Filament\Widgets;

use App\Models\Tank;
use App\Settings\TerminalSettings;
use Filament\Widgets\ChartWidget;

class TankLevelsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Tank levels (% of capacity)';

    protected ?string $pollingInterval = '10s';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $tanks = Tank::query()->active()->orderBy('code')->get();
        $high = app(TerminalSettings::class)->highLevelAlarmPercent();

        $palette = ['cpo' => '#d97706', 'cpol' => '#16a34a', 'pfad' => '#64748b'];

        return [
            'datasets' => [
                [
                    'label' => 'Fill %',
                    'data' => $tanks->map(fn (Tank $t) => $t->fillPercent())->all(),
                    'backgroundColor' => $tanks->map(fn (Tank $t) => $t->fillPercent() >= $high ? '#dc2626' : $palette[$t->commodity->value])->all(),
                    'borderRadius' => 0,
                ],
            ],
            'labels' => $tanks->map(fn (Tank $t) => "{$t->code} ({$t->commodity->shortLabel()})")->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['min' => 0, 'max' => 100, 'ticks' => ['stepSize' => 25]]],
        ];
    }
}
