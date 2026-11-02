<?php

namespace App\Filament\Resources\CargoMovements\Schemas;

use App\Enums\MovementStatus;
use App\Models\CargoMovement;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Enums\MovementDirection;

class CargoMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $mt = fn (?int $state) => $state === null ? '—' : Quantity::kg($state)->format();

        return $schema
            ->columns(3)
            ->components([
                Section::make('Movement')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reference')->copyable(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('direction')
                            ->formatStateUsing(fn (MovementDirection $state) => $state->label()),
                        TextEntry::make('tank.code')->label('Tank'),
                        TextEntry::make('commodity')
                            ->formatStateUsing(fn ($state) => $state->label()),
                        TextEntry::make('party')
                            ->label('Vessel / truck')
                            ->state(fn (CargoMovement $record) => $record->vessel?->name ?? $record->truck_registration),
                        TextEntry::make('occurred_at')->label('Event time')->dateTime('d M Y H:i:s'),
                        TextEntry::make('received_at')->label('Logged at')->dateTime('d M Y H:i:s'),
                        TextEntry::make('processed_at')->label('Processed at')->dateTime('d M Y H:i:s')->placeholder('Not yet'),
                    ]),
                Section::make('Quantities')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('gross_kg')->label('Gross')->formatStateUsing($mt),
                        TextEntry::make('loss_kg')->label('Transit loss')->formatStateUsing($mt)->placeholder('—'),
                        TextEntry::make('net_kg')->label('Net applied')->formatStateUsing($mt)->placeholder('—'),
                        TextEntry::make('balance_after_kg')->label('Tank balance after')->formatStateUsing($mt)->placeholder('—'),
                    ]),
                Section::make('Rejection')
                    ->columnSpanFull()
                    ->visible(fn (CargoMovement $record) => $record->status === MovementStatus::Rejected)
                    ->schema([
                        TextEntry::make('rejection_reason')->hiddenLabel()->color('danger'),
                    ]),
                Section::make('Approval')
                    ->columnSpanFull()
                    ->columns(3)
                    ->visible(fn (CargoMovement $record) => $record->decided_by !== null)
                    ->schema([
                        TextEntry::make('decider.name')->label('Decided by'),
                        TextEntry::make('decided_at')->label('Decided at')->dateTime('d M Y H:i'),
                        TextEntry::make('decision_note')->label('Note')->placeholder('—'),
                    ]),
            ]);
    }
}
