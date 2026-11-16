<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor\Exceptions;

use RuntimeException;

/**
 * Computing a code, a secret or the setup's QR code failed. Carries no message from the cause and
 * no previous exception: their traces could hold the secret.
 */
final class TwoFactorUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Two-factor authentication is unavailable right now');
    }
}
