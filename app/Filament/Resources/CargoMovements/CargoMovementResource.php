<?php

namespace App\Filament\Resources\CargoMovements;

use App\Enums\MovementStatus;
use App\Filament\Resources\CargoMovements\Pages\CreateCargoMovement;
use App\Filament\Resources\CargoMovements\Pages\EditCargoMovement;
use App\Filament\Resources\CargoMovements\Pages\ListCargoMovements;
use App\Filament\Resources\CargoMovements\Pages\ViewCargoMovement;
use App\Filament\Resources\CargoMovements\Schemas\CargoMovementForm;
use App\Filament\Resources\CargoMovements\Schemas\CargoMovementInfolist;
use App\Filament\Resources\CargoMovements\Tables\CargoMovementsTable;
use App\Models\CargoMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CargoMovementResource extends Resource
{
    protected static ?string $model = CargoMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?string $navigationLabel = 'Cargo movements';

    public static function getNavigationBadge(): ?string
    {
        $awaiting = CargoMovement::query()->where('status', MovementStatus::AwaitingApproval)->count();

        return $awaiting > 0 ? (string) $awaiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Releases awaiting supervisor approval';
    }

    public static function form(Schema $schema): Schema
    {
        return CargoMovementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CargoMovementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CargoMovementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCargoMovements::route('/'),
            'create' => CreateCargoMovement::route('/create'),
            'view' => ViewCargoMovement::route('/{record}'),
            'edit' => EditCargoMovement::route('/{record}/edit'),
        ];
    }
}
