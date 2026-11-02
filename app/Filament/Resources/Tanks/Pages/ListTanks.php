<?php

namespace App\Filament\Resources\Tanks\Pages;

use App\Filament\Resources\Tanks\TankResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTanks extends ListRecords
{
    protected static string $resource = TankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
