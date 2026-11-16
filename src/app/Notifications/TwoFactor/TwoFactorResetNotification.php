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
}
