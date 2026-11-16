<?php

declare(strict_types=1);

namespace App\Notifications\TwoFactor;

/**
 * Somebody gave the password and then wrong codes again and again (core#770): the password is
 * probably known, so the mail asks to change it.
 */
final class RepeatedTwoFactorCodeFailuresNotification extends TwoFactorSecurityNotification
{
    protected function type(): string
    {
        return 'repeated_code_failures';
    }

    protected function notYouKey(): string
    {
        return 'two_factor.mail.repeated_code_failures.not_you';
    }
}
