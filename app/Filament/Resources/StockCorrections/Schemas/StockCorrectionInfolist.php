<?php

namespace App\Filament\Resources\StockCorrections\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StockCorrectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tank.id')
                    ->label('Tank'),
                TextEntry::make('direction'),
                TextEntry::make('quantity_kg')
                    ->numeric(),
                TextEntry::make('reason')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('requested_by')
                    ->numeric(),
                TextEntry::make('decided_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('decided_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('decision_note')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('applied_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('balance_after_kg')
                    ->numeric()
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
