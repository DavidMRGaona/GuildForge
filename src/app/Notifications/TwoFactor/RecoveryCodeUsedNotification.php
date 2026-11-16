<?php

declare(strict_types=1);

namespace App\Notifications\TwoFactor;

/**
 * A recovery code was used to sign in, with how many are left (and advice when few are).
 */
final class RecoveryCodeUsedNotification extends TwoFactorSecurityNotification
{
    /** From this many left, the mail asks for new ones */
    public const int FEW_LEFT = 2;

    public function __construct(
        string $siteName,
        string $occurredAt,
        private readonly int $remaining,
    ) {
        parent::__construct($siteName, $occurredAt);
    }

    protected function type(): string
    {
        return 'recovery_code_used';
    }

    protected function extraLines(): array
    {
        $lines = [trans_choice('two_factor.mail.recovery_code_used.remaining', $this->remaining, ['count' => $this->remaining])];

        if ($this->remaining <= self::FEW_LEFT) {
            $lines[] = __('two_factor.mail.recovery_code_used.few_left');
        }

        return $lines;
    }
}
