<?php

namespace App\Filament\Resources\Tanks;

use App\Filament\Resources\Tanks\Pages\CreateTank;
use App\Filament\Resources\Tanks\Pages\EditTank;
use App\Filament\Resources\Tanks\Pages\ListTanks;
use App\Filament\Resources\Tanks\Schemas\TankForm;
use App\Filament\Resources\Tanks\Tables\TanksTable;
use App\Models\Tank;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TankResource extends Resource
{
    protected static ?string $model = Tank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Terminal setup';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return TankForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TanksTable::configure($table);
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
            'index' => ListTanks::route('/'),
            'create' => CreateTank::route('/create'),
            'edit' => EditTank::route('/{record}/edit'),
        ];
    }
}
