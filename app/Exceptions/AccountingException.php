<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown for user-fixable accounting config problems.
 * Caught separately from \Throwable so controllers can surface a clean message.
 */
class AccountingException extends \RuntimeException {}
