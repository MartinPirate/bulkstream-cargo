<?php

namespace App\Filament\Resources\Vessels\Pages;

use App\Filament\Resources\Vessels\VesselResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVessels extends ListRecords
{
    protected static string $resource = VesselResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
