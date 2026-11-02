<?php

namespace App\Filament\Resources\Tanks\Schemas;

use App\Models\Tank;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use MartinKiragu\TankTerminal\Enums\Commodity;

class TankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tank')
                    ->description('Stock is never typed in here: it only changes through cargo movements and approved stock corrections.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->placeholder('T-104'),
                        Select::make('commodity')
                            ->options(Commodity::options())
                            ->required()
                            ->live()
                            // Re-assigning a tank that still holds product would mix grades.
                            ->disabled(fn (?Tank $record): bool => $record !== null && $record->balance_kg > 0)
                            ->helperText(fn (Get $get): ?string => filled($get('commodity'))
                                ? Commodity::from($get('commodity'))->handlingNote()
                                : null),
                        TextInput::make('capacity_kg')
                            ->label('Capacity (MT)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->step(0.001)
                            ->suffix('MT')
                            ->formatStateUsing(fn ($state) => $state !== null ? $state / 1000 : null)
                            ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 1000))
                            ->rule(fn (?Tank $record) => function (string $attribute, $value, $fail) use ($record) {
                                if ($record !== null && ((float) $value) * 1000 < $record->balance_kg) {
                                    $fail('Capacity cannot be below the current stock of '.$record->balance()->format().'.');
                                }
                            }),
                        TextInput::make('temperature_c')
                            ->label('Temperature')
                            ->numeric()
                            ->step(0.1)
                            ->suffix('°C')
                            ->live(onBlur: true)
                            ->helperText(function (Get $get): ?string {
                                if (blank($get('commodity'))) {
                                    return null;
                                }
                                [$min, $max] = Commodity::from($get('commodity'))->storageTemperatureRange();

                                return "Recommended {$min}–{$max} °C.";
                            }),
                        Toggle::make('is_active')
                            ->label('In service')
                            ->default(true),
                    ]),
            ]);
    }
}
