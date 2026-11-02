<?php

namespace App\Filament\Resources\StockCorrections\Pages;

use App\Filament\Resources\StockCorrections\StockCorrectionResource;
use App\Models\Tank;
use App\Terminal\StockCorrectionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStockCorrection extends CreateRecord
{
    protected static string $resource = StockCorrectionResource::class;

    /**
     * Route creation through the service so status and requester are always
     * set server-side, never taken from the form.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(StockCorrectionService::class)->request(
            tank: Tank::query()->findOrFail($data['tank_id']),
            direction: $data['direction'],
            quantityKg: (int) $data['quantity_kg'],
            reason: $data['reason'],
            requester: auth()->user(),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
