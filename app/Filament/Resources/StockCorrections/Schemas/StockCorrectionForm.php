<?php

namespace App\Filament\Resources\StockCorrections\Schemas;

use App\Models\Tank;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use MartinKiragu\TankTerminal\Domain\Quantity;

class StockCorrectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request a stock correction')
                    ->description('Use when a tank dip or ullage survey disagrees with the book balance. A supervisor must approve before the balance changes.')
                    ->columns(2)
                    ->schema([
                        Select::make('tank_id')
                            ->label('Tank')
                            ->options(fn () => Tank::query()->orderBy('code')->get()
                                ->mapWithKeys(fn (Tank $t) => [$t->id => "{$t->code} · {$t->commodity->shortLabel()} · {$t->balance()->format()}"]))
                            ->required()
                            ->live()
                            ->searchable(),
                        ToggleButtons::make('direction')
                            ->options(['increase' => 'Increase stock', 'decrease' => 'Decrease stock'])
                            ->colors(['increase' => 'success', 'decrease' => 'danger'])
                            ->inline()
                            ->required()
                            ->live(),
                        TextInput::make('quantity_kg')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(0.001)
                            ->maxValue(200_000)
                            ->step(0.001)
                            ->suffix('MT')
                            ->required()
                            ->live(onBlur: true)
                            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 1000))
                            ->helperText(function (Get $get): ?string {
                                $tank = filled($get('tank_id')) ? Tank::query()->find($get('tank_id')) : null;
                                if ($tank === null || blank($get('quantity_kg')) || blank($get('direction'))) {
                                    return null;
                                }
                                $qty = Quantity::tonnes($get('quantity_kg'));
                                if ($get('direction') === 'increase') {
                                    return $qty->isGreaterThan($tank->freeCapacity())
                                        ? 'Exceeds free capacity ('.$tank->freeCapacity()->format().'); it will fail on approval.'
                                        : 'Book stock after: '.$tank->balance()->plus($qty)->format();
                                }

                                return $qty->isGreaterThan($tank->balance())
                                    ? 'More than the tank holds; it will fail on approval.'
                                    : 'Book stock after: '.$tank->balance()->minus($qty)->format();
                            }),
                        Textarea::make('reason')
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->placeholder('e.g. Dip survey 02 Nov 07:00 (surveyor ref. DS-4411) reads 12.4 MT below book.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
