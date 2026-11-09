<?php

namespace App\Filament\Resources\CommodityProfiles\Tables;

use App\Filament\Support\RichOptions;
use App\Models\CommodityProfile;
use App\Models\Tank;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Enums\Commodity;

class CommodityProfilesTable
{
    public static function configure(Table $table): Table
    {
        // One grouped query feeds the stock columns for all three rows.
        $totals = null;
        $row = function (CommodityProfile $profile) use (&$totals) {
            $totals ??= Tank::query()
                ->toBase()
                ->selectRaw('commodity, COUNT(*) AS tanks, COALESCE(SUM(balance_kg), 0) AS stock_kg, COALESCE(SUM(capacity_kg), 0) AS capacity_kg')
                ->groupBy('commodity')
                ->get()
                ->keyBy('commodity');

            return $totals->get($profile->code->value);
        };

        return $table
            ->paginated(false)
            ->defaultSort('id')
            ->columns([
                TextColumn::make('code')
                    ->badge()
                    ->formatStateUsing(fn (Commodity $state) => $state->shortLabel())
                    ->color(fn (Commodity $state) => RichOptions::commodityColor($state)),
                TextColumn::make('name')
                    ->weight('bold')
                    ->description(fn (CommodityProfile $record) => $record->handling_note)
                    ->wrap(),
                TextColumn::make('storage_band')
                    ->label('Storage band')
                    ->state(fn (CommodityProfile $record) => $record->temperatureBandLabel()),
                IconColumn::make('requires_heating')
                    ->label('Heated')
                    ->boolean(),
                TextColumn::make('tanks')
                    ->label('Tanks')
                    ->state(fn (CommodityProfile $record) => (int) ($row($record)?->tanks ?? 0)),
                TextColumn::make('stock')
                    ->label('In storage')
                    ->state(fn (CommodityProfile $record) => Quantity::kg((int) ($row($record)?->stock_kg ?? 0))->format()
                        .' of '.Quantity::kg((int) ($row($record)?->capacity_kg ?? 0))->format()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
