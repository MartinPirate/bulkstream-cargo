<?php

namespace App\Filament\Resources\CargoMovements\Tables;

use App\Enums\MovementStatus;
use App\Models\CargoMovement;
use App\Models\Tank;
use App\Terminal\CargoPipeline;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Enums\Commodity;
use MartinKiragu\TankTerminal\Enums\MovementDirection;

class CargoMovementsTable
{
    public static function configure(Table $table): Table
    {
        $mt = fn (?int $state) => $state === null ? '—' : Quantity::kg($state)->format();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tank', 'vessel']))
            ->poll('10s')
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('reference')->searchable()->weight('medium'),
                TextColumn::make('occurred_at')->label('Event time')->dateTime('d M H:i:s')->sortable(),
                TextColumn::make('received_at')->label('Logged')->dateTime('d M H:i:s')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('direction')
                    ->badge()
                    ->formatStateUsing(fn (MovementDirection $state) => $state === MovementDirection::Inbound ? 'Discharge' : 'Loading')
                    ->color(fn (MovementDirection $state) => $state === MovementDirection::Inbound ? 'info' : 'gray')
                    ->icon(fn (MovementDirection $state) => $state === MovementDirection::Inbound ? 'heroicon-o-arrow-down-tray' : 'heroicon-o-truck'),
                TextColumn::make('tank.code')->label('Tank')->sortable(),
                TextColumn::make('commodity')->formatStateUsing(fn (Commodity $state) => $state->shortLabel()),
                TextColumn::make('party')
                    ->label('Vessel / truck')
                    ->state(fn (CargoMovement $record) => $record->vessel?->name ?? $record->truck_registration),
                TextColumn::make('gross_kg')->label('Gross')->formatStateUsing($mt)->alignEnd(),
                TextColumn::make('loss_kg')->label('Loss')->formatStateUsing($mt)->alignEnd()
                    ->toggleable(),
                TextColumn::make('net_kg')->label('Net')->formatStateUsing($mt)->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->tooltip(fn (CargoMovement $record) => $record->rejection_reason ?? $record->decision_note),
            ])
            ->filters([
                SelectFilter::make('status')->options(MovementStatus::class),
                SelectFilter::make('direction')->options([
                    MovementDirection::Inbound->value => 'Discharge',
                    MovementDirection::Outbound->value => 'Loading',
                ]),
                SelectFilter::make('tank_id')->label('Tank')
                    ->options(fn () => Tank::query()->orderBy('code')->pluck('code', 'id')),
                SelectFilter::make('commodity')->options(Commodity::options()),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approveAction(),
                self::declineAction(),
                EditAction::make(),
            ]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (CargoMovement $record) => auth()->user()->can('approve', $record))
            ->requiresConfirmation()
            ->modalDescription(fn (CargoMovement $record) => sprintf(
                'Release %s from %s to %s. It enters the pipeline immediately.',
                Quantity::kg($record->gross_kg)->format(),
                $record->tank->code,
                $record->truck_registration,
            ))
            ->schema([
                Textarea::make('note')->label('Note (optional)')->maxLength(500),
            ])
            ->action(function (CargoMovement $record, array $data): void {
                app(CargoPipeline::class)->approve($record, auth()->user(), $data['note'] ?? null);

                Notification::make()->title('Release approved')->body('Queued for processing.')->success()->send();
            });
    }

    public static function declineAction(): Action
    {
        return Action::make('decline')
            ->label('Decline')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (CargoMovement $record) => auth()->user()->can('approve', $record))
            ->schema([
                Textarea::make('note')->label('Reason')->required()->maxLength(500),
            ])
            ->action(function (CargoMovement $record, array $data): void {
                app(CargoPipeline::class)->decline($record, auth()->user(), $data['note']);

                Notification::make()->title('Release declined')->warning()->send();
            });
    }
}
