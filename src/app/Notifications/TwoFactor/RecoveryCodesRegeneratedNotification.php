<?php

declare(strict_types=1);

namespace App\Notifications\TwoFactor;

/**
 * The person's two-factor authentication was given new recovery codes, so the old ones stopped working.
 */
final class RecoveryCodesRegeneratedNotification extends TwoFactorSecurityNotification
{
    protected function type(): string
    {
        return 'recovery_codes_regenerated';
    }
}
