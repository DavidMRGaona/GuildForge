<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor\Exceptions;

use App\Domain\TwoFactor\TwoFactorFailure;
use DomainException;

/**
 * A change to someone's two-factor authentication was refused. The message names the reason
 * only: never a code, a secret or the account.
 */
final class TwoFactorException extends DomainException
{
    private function __construct(
        public readonly TwoFactorFailure $reason,
    ) {
        parent::__construct(sprintf('Two-factor authentication change refused: %s', $reason->value));
    }

    public static function because(TwoFactorFailure $reason): self
    {
        return new self($reason);
    }
}
