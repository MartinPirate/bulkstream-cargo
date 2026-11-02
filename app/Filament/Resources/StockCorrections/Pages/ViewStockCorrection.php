<?php

namespace App\Filament\Resources\StockCorrections\Pages;

use App\Filament\Resources\StockCorrections\StockCorrectionResource;
use App\Filament\Resources\StockCorrections\Tables\StockCorrectionsTable;
use Filament\Resources\Pages\ViewRecord;

class ViewStockCorrection extends ViewRecord
{
    protected static string $resource = StockCorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StockCorrectionsTable::approveAction(),
            StockCorrectionsTable::declineAction(),
        ];
    }
}
