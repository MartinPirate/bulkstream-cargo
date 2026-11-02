<?php

namespace App\Filament\Resources\StockCorrections\Pages;

use App\Filament\Resources\StockCorrections\StockCorrectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockCorrections extends ListRecords
{
    protected static string $resource = StockCorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
