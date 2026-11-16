<?php

declare(strict_types=1);

namespace App\Notifications\TwoFactor;

/**
 * The person's authenticator app was changed.
 */
final class TwoFactorAppChangedNotification extends TwoFactorSecurityNotification
{
    protected function type(): string
    {
        return 'app_changed';
    }
}
