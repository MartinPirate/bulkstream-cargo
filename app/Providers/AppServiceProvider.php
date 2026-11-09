<?php

namespace App\Providers;

use App\Models\CommodityProfile;
use App\Settings\TerminalSettings;
use App\Terminal\EloquentMovementLedger;
use App\Terminal\EloquentTankRepository;
use App\Terminal\LaravelTransactionManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use MartinKiragu\TankTerminal\Contracts\MovementLedger;
use MartinKiragu\TankTerminal\Contracts\TankRepository;
use MartinKiragu\TankTerminal\Contracts\TransactionManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bind the terminal core's ports to their MySQL adapters.
     */
    public function register(): void
    {
        $this->app->singleton(TankRepository::class, EloquentTankRepository::class);
        $this->app->singleton(MovementLedger::class, EloquentMovementLedger::class);
        $this->app->singleton(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->singleton(TerminalSettings::class);
    }

    public function boot(): void
    {
        // Catch N+1 queries during development.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Queue workers are long-lived: drop the per-process profile memo before
        // each job so an admin's edit in the web process is never read stale.
        Queue::before(fn () => CommodityProfile::flushMemo());
    }
}
