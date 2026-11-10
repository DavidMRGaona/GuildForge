<?php

declare(strict_types=1);

namespace App\Domain\Media\Exceptions;

use RuntimeException;

/**
 * Deleting from the media library is off for this installation (switch off or no storage prefix).
 */
final class MediaDeletionDisabledException extends RuntimeException
{
    public static function create(): self
    {
        return new self('Deleting from the media library is disabled for this installation.');
    }
}
