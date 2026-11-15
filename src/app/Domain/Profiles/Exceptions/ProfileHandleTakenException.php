<?php

declare(strict_types=1);

namespace App\Domain\Profiles\Exceptions;

use DomainException;

/**
 * The address chosen is held, or was held, by somebody else, or is a reserved word: it is never given.
 */
final class ProfileHandleTakenException extends DomainException
{
    public static function create(): self
    {
        return new self('This profile address is not available.');
    }
}
