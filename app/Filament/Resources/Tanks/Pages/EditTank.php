<?php

namespace App\Filament\Resources\Tanks\Pages;

use App\Filament\Resources\Tanks\TankResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTank extends EditRecord
{
    protected static string $resource = TankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
