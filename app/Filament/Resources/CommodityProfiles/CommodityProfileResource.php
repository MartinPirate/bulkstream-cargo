<?php

namespace App\Filament\Resources\CommodityProfiles;

use App\Filament\Resources\CommodityProfiles\Pages\EditCommodityProfile;
use App\Filament\Resources\CommodityProfiles\Pages\ListCommodityProfiles;
use App\Filament\Resources\CommodityProfiles\Schemas\CommodityProfileForm;
use App\Filament\Resources\CommodityProfiles\Tables\CommodityProfilesTable;
use App\Models\CommodityProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CommodityProfileResource extends Resource
{
    protected static ?string $model = CommodityProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Terminal setup';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Commodity profiles';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CommodityProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommodityProfilesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommodityProfiles::route('/'),
            'edit' => EditCommodityProfile::route('/{record}/edit'),
        ];
    }
}
