<?php

declare(strict_types=1);

namespace App\Domain\Bots\Exceptions;

use RuntimeException;

/**
 * The key names no bot an enabled module registers (never registered, or its module is disabled).
 */
final class UnknownBotException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No enabled module registers the bot [{$key}].");
    }
}
