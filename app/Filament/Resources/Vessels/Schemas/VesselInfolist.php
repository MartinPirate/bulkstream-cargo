<?php

namespace App\Filament\Resources\Vessels\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class VesselInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('imo_number')
                    ->placeholder('-'),
                TextEntry::make('voyage_number')
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('eta')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('berthed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('departed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
