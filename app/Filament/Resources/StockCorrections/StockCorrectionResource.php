<?php

namespace App\Filament\Resources\StockCorrections;

use App\Filament\Resources\StockCorrections\Pages\CreateStockCorrection;
use App\Filament\Resources\StockCorrections\Pages\EditStockCorrection;
use App\Filament\Resources\StockCorrections\Pages\ListStockCorrections;
use App\Filament\Resources\StockCorrections\Pages\ViewStockCorrection;
use App\Filament\Resources\StockCorrections\Schemas\StockCorrectionForm;
use App\Filament\Resources\StockCorrections\Schemas\StockCorrectionInfolist;
use App\Filament\Resources\StockCorrections\Tables\StockCorrectionsTable;
use App\Models\StockCorrection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StockCorrectionResource extends Resource
{
    protected static ?string $model = StockCorrection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return StockCorrectionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockCorrectionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockCorrectionsTable::configure($table);
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
            'index' => ListStockCorrections::route('/'),
            'create' => CreateStockCorrection::route('/create'),
            'view' => ViewStockCorrection::route('/{record}'),
            'edit' => EditStockCorrection::route('/{record}/edit'),
        ];
    }
}
