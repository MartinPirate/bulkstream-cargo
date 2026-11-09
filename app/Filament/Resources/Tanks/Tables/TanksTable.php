<?php

namespace App\Filament\Resources\Tanks\Tables;

use App\Filament\Support\RichOptions;
use App\Models\Tank;
use App\Settings\TerminalSettings;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use MartinKiragu\TankTerminal\Enums\Commodity;

class TanksTable
{
    public static function configure(Table $table): Table
    {
        $settings = app(TerminalSettings::class);

        return $table
            ->poll('10s')
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('commodity')
                    ->badge()
                    ->formatStateUsing(fn (Commodity $state) => $state->shortLabel())
                    ->tooltip(fn (Tank $record) => $record->commodity->label())
                    ->color(fn (Commodity $state) => RichOptions::commodityColor($state)),
                TextColumn::make('balance_kg')
                    ->label('Stock')
                    ->formatStateUsing(fn (Tank $record) => $record->balance()->format())
                    ->sortable(),
                TextColumn::make('capacity_kg')
                    ->label('Capacity')
                    ->formatStateUsing(fn (Tank $record) => $record->capacity()->format())
                    ->sortable(),
                TextColumn::make('fill')
                    ->label('Fill')
                    ->state(fn (Tank $record) => $record->fillPercent().'%')
                    ->badge()
                    ->color(fn (Tank $record) => match (true) {
                        $record->fillPercent() >= $settings->highLevelAlarmPercent() => 'danger',
                        $record->fillPercent() <= $settings->lowLevelAlarmPercent() => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('free')
                    ->label('Ullage (free)')
                    ->state(fn (Tank $record) => $record->freeCapacity()->format()),
                TextColumn::make('temperature_c')
                    ->label('Temp')
                    ->suffix(' °C')
                    ->color(fn (Tank $record) => $record->isTemperatureOutOfRange() ? 'danger' : null)
                    ->icon(fn (Tank $record) => $record->isTemperatureOutOfRange() ? 'heroicon-o-exclamation-triangle' : null),
                IconColumn::make('is_active')
                    ->label('In service')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('commodity')->options(Commodity::options()),
                TernaryFilter::make('is_active')->label('In service'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
