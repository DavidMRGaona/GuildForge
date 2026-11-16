<?php

declare(strict_types=1);

namespace App\Notifications\TwoFactor;

/**
 * The person's two-factor authentication was turned off.
 */
final class TwoFactorDisabledNotification extends TwoFactorSecurityNotification
{
    protected function type(): string
    {
        return 'disabled';
    }
}
