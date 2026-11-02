<?php

namespace App\Filament\Resources\StockCorrections\Tables;

use App\Enums\CorrectionStatus;
use App\Models\StockCorrection;
use App\Terminal\StockCorrectionService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MartinKiragu\TankTerminal\Domain\Quantity;

class StockCorrectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tank', 'requester', 'decider']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Requested')->dateTime('d M H:i')->sortable(),
                TextColumn::make('tank.code')->label('Tank'),
                TextColumn::make('direction')->badge()
                    ->color(fn (string $state) => $state === 'increase' ? 'success' : 'danger'),
                TextColumn::make('quantity_kg')->label('Quantity')
                    ->formatStateUsing(fn (int $state) => Quantity::kg($state)->format()),
                TextColumn::make('reason')->limit(40)->tooltip(fn (StockCorrection $r) => $r->reason),
                TextColumn::make('requester.name')->label('Requested by'),
                TextColumn::make('status')->badge()
                    ->tooltip(fn (StockCorrection $r) => $r->decision_note),
                TextColumn::make('decider.name')->label('Decided by')->placeholder('—'),
                TextColumn::make('balance_after_kg')->label('Balance after')->placeholder('—')
                    ->formatStateUsing(fn (?int $state) => $state === null ? '—' : Quantity::kg($state)->format()),
            ])
            ->filters([
                SelectFilter::make('status')->options(CorrectionStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                self::approveAction(),
                self::declineAction(),
            ]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (StockCorrection $record) => auth()->user()->can('approve', $record))
            ->requiresConfirmation()
            ->schema([Textarea::make('note')->label('Note (optional)')->maxLength(500)])
            ->action(function (StockCorrection $record, array $data): void {
                $result = app(StockCorrectionService::class)->approve($record, auth()->user(), $data['note'] ?? null);

                if ($result->status === CorrectionStatus::Applied) {
                    Notification::make()->title('Correction applied')
                        ->body('New balance: '.Quantity::kg($result->balance_after_kg)->format())->success()->send();
                } else {
                    Notification::make()->title('Correction could not be applied')
                        ->body($result->decision_note)->danger()->send();
                }
            });
    }

    public static function declineAction(): Action
    {
        return Action::make('decline')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (StockCorrection $record) => auth()->user()->can('approve', $record))
            ->schema([Textarea::make('note')->label('Reason')->required()->maxLength(500)])
            ->action(function (StockCorrection $record, array $data): void {
                app(StockCorrectionService::class)->decline($record, auth()->user(), $data['note']);
                Notification::make()->title('Correction declined')->warning()->send();
            });
    }
}
