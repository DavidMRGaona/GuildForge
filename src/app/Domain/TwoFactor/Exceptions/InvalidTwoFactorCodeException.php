<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor\Exceptions;

use DomainException;

/**
 * What was typed is not shaped like a code. The message never repeats it: codes are secrets.
 */
final class InvalidTwoFactorCodeException extends DomainException
{
    public static function totp(): self
    {
        return new self('An authenticator code has six digits');
    }

    public static function recovery(): self
    {
        return new self('A recovery code has two groups of five characters');
    }
}
