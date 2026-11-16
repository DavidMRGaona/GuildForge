<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor\Exceptions;

use App\Domain\TwoFactor\TwoFactorFailure;
use DomainException;

/**
 * Two-factor authentication refused or could not do something. The message names the reason
 * only: never a code, a secret or the account. TwoFactorUnavailableException (reason Unavailable)
 * is the one subclass, so catching this class catches every two-factor failure.
 */
class TwoFactorException extends DomainException
{
    protected function __construct(
        public readonly TwoFactorFailure $reason,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : sprintf('Two-factor authentication change refused: %s', $reason->value));
    }

    /**
     * Unavailable is always a TwoFactorUnavailableException, the class callers (and the session
     * check) recognise it by; any other reason is a refusal.
     */
    public static function because(TwoFactorFailure $reason): self
    {
        if ($reason === TwoFactorFailure::Unavailable) {
            return new TwoFactorUnavailableException;
        }

        return new self($reason);
    }
}
