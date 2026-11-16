<?php

declare(strict_types=1);

namespace App\Notifications\TwoFactor;

/**
 * The administration took away the person's two-factor authentication (core#771).
 */
final class TwoFactorResetNotification extends TwoFactorSecurityNotification
{
    protected function type(): string
    {
        return 'reset';
    }

    /**
     * It was the administration, not the person, who did it: the general line («si no has sido tú») would not fit.
     */
    protected function notYouKey(): string
    {
        return 'two_factor.mail.reset.not_you';
    }
}
