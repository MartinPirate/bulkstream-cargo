<?php

namespace App\Filament\Resources\CargoMovements\Pages;

use App\Filament\Resources\CargoMovements\CargoMovementResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCargoMovement extends EditRecord
{
    protected static string $resource = CargoMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
