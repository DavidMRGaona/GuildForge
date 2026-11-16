<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor\Exceptions;

use App\Domain\TwoFactor\TwoFactorFailure;

/**
 * Computing a code, a secret or the setup's QR code failed, or the session seal could not be read.
 * A TwoFactorException with reason Unavailable, so callers catch it with the rest. Carries no
 * message from the cause and no previous exception: their traces could hold the secret.
 */
final class TwoFactorUnavailableException extends TwoFactorException
{
    public function __construct()
    {
        parent::__construct(TwoFactorFailure::Unavailable, 'Two-factor authentication is unavailable right now');
    }
}
