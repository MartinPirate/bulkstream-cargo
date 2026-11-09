<?php

namespace App\Filament\Resources\CommodityProfiles\Pages;

use App\Filament\Resources\CommodityProfiles\CommodityProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListCommodityProfiles extends ListRecords
{
    protected static string $resource = CommodityProfileResource::class;

    public function getSubheading(): ?string
    {
        return 'The terminal handles three products. Their codes are fixed by the domain rules; their storage handling is tuned here.';
    }
}
