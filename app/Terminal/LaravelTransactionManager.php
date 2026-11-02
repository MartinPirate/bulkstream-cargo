<?php

namespace App\Terminal;

use Illuminate\Support\Facades\DB;
use MartinKiragu\TankTerminal\Contracts\TransactionManager;

class LaravelTransactionManager implements TransactionManager
{
    /**
     * Laravel retries the closure when MySQL reports a deadlock, so a
     * transient lock conflict between workers does not surface as a failure.
     */
    public function __construct(private readonly int $deadlockAttempts = 3) {}

    public function run(callable $callback): mixed
    {
        return DB::transaction($callback, $this->deadlockAttempts);
    }
}
