<?php

namespace App\Filament\Resources\Vessels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use MartinKiragu\TankTerminal\Domain\Quantity;

class VesselsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('imo_number')
                    ->searchable(),
                TextColumn::make('voyage_number')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('eta')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('berthed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('departed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('discharges_sum_net_kg')
                    ->label('Discharged (net)')
                    ->sum('discharges', 'net_kg')
                    ->formatStateUsing(fn ($state) => Quantity::kg((int) $state)->format()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
