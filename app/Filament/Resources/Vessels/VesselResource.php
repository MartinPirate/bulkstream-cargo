<?php

namespace App\Filament\Resources\Vessels;

use App\Filament\Resources\Vessels\Pages\CreateVessel;
use App\Filament\Resources\Vessels\Pages\EditVessel;
use App\Filament\Resources\Vessels\Pages\ListVessels;
use App\Filament\Resources\Vessels\Pages\ViewVessel;
use App\Filament\Resources\Vessels\Schemas\VesselForm;
use App\Filament\Resources\Vessels\Schemas\VesselInfolist;
use App\Filament\Resources\Vessels\Tables\VesselsTable;
use App\Models\Vessel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VesselResource extends Resource
{
    protected static ?string $model = Vessel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return VesselForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VesselInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VesselsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVessels::route('/'),
            'create' => CreateVessel::route('/create'),
            'view' => ViewVessel::route('/{record}'),
            'edit' => EditVessel::route('/{record}/edit'),
        ];
    }
}
