<?php

declare(strict_types=1);

namespace App\Domain\Profiles\Exceptions;

use RuntimeException;

/**
 * Whether accounts show their membership could not be read (core#824): the role setting, the database or
 * the two-factor reader failed. It carries no cause and no detail, since a message may hold what a person
 * can see; the answer «does not show it» is never given instead, because it would be taken for a real one.
 */
final class ProfileMembershipDisplayUnavailableException extends RuntimeException
{
    public static function unreadable(): self
    {
        return new self('Whether the accounts show their membership could not be read.');
    }
}
