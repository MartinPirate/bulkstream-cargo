<?php

namespace App\Filament\Resources\Vessels\Schemas;

use App\Enums\VesselStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VesselForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('imo_number')
                    ->label('IMO number')
                    ->rule('digits:7')
                    ->unique(ignoreRecord: true),
                TextInput::make('voyage_number'),
                Select::make('status')
                    ->options(VesselStatus::class)
                    ->default('expected')
                    ->required(),
                DateTimePicker::make('eta'),
                DateTimePicker::make('berthed_at'),
                DateTimePicker::make('departed_at'),
            ]);
    }
}
