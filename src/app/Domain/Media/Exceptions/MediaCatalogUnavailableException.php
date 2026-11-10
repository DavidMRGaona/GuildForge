<?php

declare(strict_types=1);

namespace App\Domain\Media\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The media catalog could not answer reliably; nothing it returned (or did not return) can be trusted.
 */
final class MediaCatalogUnavailableException extends RuntimeException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self('Media catalog unavailable: '.$reason, 0, $previous);
    }
}
