<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CargoMovements\CargoMovementResource;
use App\Models\CargoMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Enums\MovementDirection;

class RecentMovements extends TableWidget
{
    protected static ?int $sort = 4;

    protected ?string $pollingInterval = '10s';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest processed movements')
            ->query(fn (): Builder => CargoMovement::query()
                ->with('tank')
                ->whereNotNull('processed_at')
                ->latest('processed_at')
                ->limit(8))
            ->paginated(false)
            ->recordUrl(fn (CargoMovement $record) => CargoMovementResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('tank.code')->label('Tank'),
                TextColumn::make('direction')
                    ->formatStateUsing(fn (MovementDirection $state) => $state === MovementDirection::Inbound ? '↓ Discharge' : '↑ Loading'),
                TextColumn::make('net_kg')->label('Net')
                    ->state(fn (CargoMovement $r) => Quantity::kg($r->net_kg ?? $r->gross_kg)->format()),
                TextColumn::make('status')->badge(),
                TextColumn::make('processed_at')->label('At')->since(),
            ]);
    }
}
