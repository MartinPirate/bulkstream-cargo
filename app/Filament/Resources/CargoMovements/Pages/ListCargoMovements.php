<?php

namespace App\Filament\Resources\CargoMovements\Pages;

use App\Enums\MovementStatus;
use App\Filament\Resources\CargoMovements\CargoMovementResource;
use App\Models\CargoMovement;
use App\Terminal\CargoPipeline;
use App\Terminal\PipelineSimulator;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCargoMovements extends ListRecords
{
    protected static string $resource = CargoMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('simulate')
                ->label('Simulate pipeline feed')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->visible(fn () => auth()->user()->canApprove())
                ->modalDescription('Emits a burst of meter and weighbridge events logged out of chronological order. Watch the engine re-sequence them and block overflows.')
                ->schema([
                    TextInput::make('count')->label('Events')->numeric()->default(12)->minValue(1)->maxValue(50)->required(),
                ])
                ->action(function (array $data): void {
                    $emitted = app(PipelineSimulator::class)->emit((int) $data['count']);

                    Notification::make()
                        ->title("{$emitted->count()} events logged")
                        ->body('Pending events are applied in event-time order by the queue worker.')
                        ->success()
                        ->send();
                }),
            Action::make('process')
                ->label('Process pending now')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->visible(fn () => auth()->user()->canApprove())
                ->action(function (): void {
                    $report = app(CargoPipeline::class)->processPending();

                    Notification::make()
                        ->title(sprintf('Applied %d · Rejected %d', count($report->applied), count($report->rejected)))
                        ->body($report->rejected === [] ? null : implode("\n", array_slice($report->rejected, 0, 5)))
                        ->color($report->rejected === [] ? 'success' : 'warning')
                        ->send();
                }),
            CreateAction::make()->label('Record movement'),
        ];
    }

    public function getTabs(): array
    {
        $tab = fn (MovementStatus $status) => Tab::make($status->getLabel())
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));

        return [
            'all' => Tab::make('All'),
            'awaiting' => $tab(MovementStatus::AwaitingApproval)
                ->badge(fn () => CargoMovement::query()->where('status', MovementStatus::AwaitingApproval)->count() ?: null),
            'pending' => $tab(MovementStatus::Pending),
            'applied' => $tab(MovementStatus::Applied),
            'rejected' => $tab(MovementStatus::Rejected),
        ];
    }
}
