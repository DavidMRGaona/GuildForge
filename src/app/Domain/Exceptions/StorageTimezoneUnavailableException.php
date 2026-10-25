<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use RuntimeException;
use Throwable;

final class StorageTimezoneUnavailableException extends RuntimeException
{
    public static function unreadable(Throwable $previous): self
    {
        return new self(
            'The storage timezone marker could not be read; refusing to guess a zone that would misread every stored date.',
            0,
            $previous,
        );
    }

    public static function unsupported(string $zone): self
    {
        return new self(sprintf('Unsupported storage timezone "%s".', $zone));
    }
}
