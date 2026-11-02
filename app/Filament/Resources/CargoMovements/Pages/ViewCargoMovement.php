<?php

namespace App\Filament\Resources\CargoMovements\Pages;

use App\Filament\Resources\CargoMovements\CargoMovementResource;
use App\Filament\Resources\CargoMovements\Tables\CargoMovementsTable;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCargoMovement extends ViewRecord
{
    protected static string $resource = CargoMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CargoMovementsTable::approveAction(),
            CargoMovementsTable::declineAction(),
            EditAction::make(),
        ];
    }
}
