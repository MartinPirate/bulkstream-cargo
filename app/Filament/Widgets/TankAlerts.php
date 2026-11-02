<?php

namespace App\Filament\Widgets;

use App\Models\Tank;
use App\Settings\TerminalSettings;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tanks needing attention: high level, low level, or product outside its
 * storage temperature band.
 */
class TankAlerts extends TableWidget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        $settings = app(TerminalSettings::class);
        $high = $settings->highLevelAlarmPercent();
        $low = $settings->lowLevelAlarmPercent();

        $alertIds = Tank::query()->active()->get()
            ->filter(fn (Tank $t) => $t->fillPercent() >= $high
                || ($t->balance_kg > 0 && $t->fillPercent() <= $low)
                || $t->isTemperatureOutOfRange())
            ->pluck('id');

        return $table
            ->heading('Tank alerts')
            ->query(fn (): Builder => Tank::query()->whereIn('id', $alertIds))
            ->emptyStateHeading('All tanks within limits')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated(false)
            ->columns([
                TextColumn::make('code')->weight('bold'),
                TextColumn::make('alert')
                    ->badge()
                    ->state(fn (Tank $t) => match (true) {
                        $t->fillPercent() >= $high => "High level {$t->fillPercent()}%",
                        $t->isTemperatureOutOfRange() => "Temp {$t->temperature_c} °C out of band",
                        default => "Low level {$t->fillPercent()}%",
                    })
                    ->color(fn (Tank $t) => $t->fillPercent() >= $high || $t->isTemperatureOutOfRange() ? 'danger' : 'warning'),
                TextColumn::make('free')->label('Free')->state(fn (Tank $t) => $t->freeCapacity()->format()),
            ]);
    }
}
