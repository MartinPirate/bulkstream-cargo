<?php

namespace App\Filament\Resources\Vessels\Pages;

use App\Filament\Resources\Vessels\VesselResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVessel extends ViewRecord
{
    protected static string $resource = VesselResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
