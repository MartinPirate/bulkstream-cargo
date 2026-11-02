<?php

namespace App\Filament\Widgets;

use App\Enums\MovementStatus;
use App\Models\CargoMovement;
use App\Models\Tank;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use MartinKiragu\TankTerminal\Domain\Quantity;
use MartinKiragu\TankTerminal\Enums\Commodity;

class TerminalStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $byCommodity = Tank::query()->active()
            ->toBase()
            ->selectRaw('commodity, SUM(balance_kg) AS stock, SUM(capacity_kg) AS capacity')
            ->groupBy('commodity')
            ->get()
            ->keyBy('commodity');

        $stats = [];
        foreach (Commodity::cases() as $commodity) {
            $row = $byCommodity->get($commodity->value);
            $stock = (int) ($row->stock ?? 0);
            $capacity = (int) ($row->capacity ?? 0);
            $fill = $capacity > 0 ? round($stock / $capacity * 100, 1) : 0;

            $stats[] = Stat::make($commodity->label(), Quantity::kg($stock)->format())
                ->description("{$fill}% of ".Quantity::kg($capacity)->format().' shore capacity')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color($fill >= 90 ? 'danger' : 'primary');
        }

        $awaiting = CargoMovement::query()->where('status', MovementStatus::AwaitingApproval)->count();
        $pending = CargoMovement::query()->where('status', MovementStatus::Pending)->count();
        $rejected24h = CargoMovement::query()
            ->where('status', MovementStatus::Rejected)
            ->where('processed_at', '>=', now()->subDay())
            ->count();

        $stats[] = Stat::make('Awaiting approval', (string) $awaiting)
            ->description($pending > 0 ? "{$pending} queued for processing" : 'Pipeline idle')
            ->descriptionIcon('heroicon-m-check-badge')
            ->color($awaiting > 0 ? 'info' : 'gray');

        $stats[] = Stat::make('Blocked (24h)', (string) $rejected24h)
            ->description('Overflow, short-stock or wrong-product attempts')
            ->descriptionIcon('heroicon-m-shield-exclamation')
            ->color($rejected24h > 0 ? 'danger' : 'success');

        return $stats;
    }
}
