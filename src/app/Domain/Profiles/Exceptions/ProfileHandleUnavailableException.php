<?php

declare(strict_types=1);

namespace App\Domain\Profiles\Exceptions;

use RuntimeException;

/**
 * Every candidate address was taken or reserved, even the random ones: the profile is left for the
 * daily `profiles:create-missing` run.
 */
final class ProfileHandleUnavailableException extends RuntimeException
{
    public static function forUser(string $userId): self
    {
        return new self(sprintf('No free profile address was found for user %s.', $userId));
    }
}
