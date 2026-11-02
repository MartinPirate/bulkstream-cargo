<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Contracts;

interface TransactionManager
{
    /**
     * Run the callback atomically: commit on success, roll back and rethrow on failure.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
