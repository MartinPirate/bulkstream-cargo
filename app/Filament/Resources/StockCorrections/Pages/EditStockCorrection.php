<?php

namespace App\Filament\Resources\StockCorrections\Pages;

use App\Filament\Resources\StockCorrections\StockCorrectionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditStockCorrection extends EditRecord
{
    protected static string $resource = StockCorrectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
