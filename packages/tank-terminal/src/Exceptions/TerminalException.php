<?php

declare(strict_types=1);

namespace MartinKiragu\TankTerminal\Exceptions;

use DomainException;

/**
 * Base type for every business-rule violation raised by the terminal core.
 * Catching this one type lets callers separate rule breaches from infrastructure failures.
 */
abstract class TerminalException extends DomainException {}
