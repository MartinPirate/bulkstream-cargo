<?php

namespace App\Filament\Resources\CommodityProfiles\Schemas;

use App\Models\CommodityProfile;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CommodityProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Commodity profile')
                    ->description('Tanks holding this product raise an alert when their temperature leaves the band below.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Code')
                            ->badge()
                            ->formatStateUsing(fn (CommodityProfile $record) => $record->code->shortLabel())
                            ->helperText('Fixed: the pipeline rules are keyed on it.'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(80),
                        TextInput::make('storage_temp_min_c')
                            ->label('Storage band: minimum')
                            ->numeric()
                            ->step(0.1)
                            ->minValue(-20)
                            ->maxValue(150)
                            ->suffix('°C')
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('storage_temp_max_c')
                            ->label('Storage band: maximum')
                            ->numeric()
                            ->step(0.1)
                            ->minValue(-20)
                            ->maxValue(150)
                            ->suffix('°C')
                            ->required()
                            ->live(onBlur: true)
                            ->gt('storage_temp_min_c')
                            ->validationMessages(['gt' => 'The maximum must be above the minimum.']),
                        Toggle::make('requires_heating')
                            ->label('Requires tank heating')
                            ->helperText(fn (Get $get) => $get('requires_heating')
                                ? 'Steam coils must hold the band to keep it pumpable.'
                                : 'Stored at ambient temperature.')
                            ->live()
                            ->columnSpanFull(),
                        Textarea::make('handling_note')
                            ->label('Handling note')
                            ->required()
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
