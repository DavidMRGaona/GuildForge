<?php

declare(strict_types=1);

namespace App\Infrastructure\TwoFactor\Listeners;

use App\Application\Services\SiteBrandingServiceInterface;
use App\Domain\TwoFactor\Events\RecoveryCodesRegenerated;
use App\Domain\TwoFactor\Events\RecoveryCodeUsed;
use App\Domain\TwoFactor\Events\RepeatedTwoFactorCodeFailures;
use App\Domain\TwoFactor\Events\TwoFactorAppChanged;
use App\Domain\TwoFactor\Events\TwoFactorDisabled;
use App\Domain\TwoFactor\Events\TwoFactorEnabled;
use App\Domain\TwoFactor\Events\TwoFactorReset;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\TwoFactor\RecoveryCodesRegeneratedNotification;
use App\Notifications\TwoFactor\RecoveryCodeUsedNotification;
use App\Notifications\TwoFactor\RepeatedTwoFactorCodeFailuresNotification;
use App\Notifications\TwoFactor\TwoFactorAppChangedNotification;
use App\Notifications\TwoFactor\TwoFactorDisabledNotification;
use App\Notifications\TwoFactor\TwoFactorEnabledNotification;
use App\Notifications\TwoFactor\TwoFactorResetNotification;
use App\Notifications\TwoFactor\TwoFactorSecurityNotification;
use Closure;

/**
 * Mails the account owner about every change to their two-factor authentication (category
 * core.account-security, always sent). The events are dispatched after the commit.
 */
final readonly class SendTwoFactorSecurityNotice
{
    public function __construct(
        private SiteBrandingServiceInterface $branding,
    ) {}

    public function enabled(TwoFactorEnabled $event): void
    {
        $this->send($event->userId, static fn (string $site, string $at): TwoFactorSecurityNotification => new TwoFactorEnabledNotification($site, $at));
    }

    public function disabled(TwoFactorDisabled $event): void
    {
        $this->send($event->userId, static fn (string $site, string $at): TwoFactorSecurityNotification => new TwoFactorDisabledNotification($site, $at));
    }

    public function reset(TwoFactorReset $event): void
    {
        // Never names who did it: the person is told the administration did, and that is all they need
        $this->send($event->userId, static fn (string $site, string $at): TwoFactorSecurityNotification => new TwoFactorResetNotification($site, $at));
    }

    public function repeatedCodeFailures(RepeatedTwoFactorCodeFailures $event): void
    {
        $this->send($event->userId, static fn (string $site, string $at): TwoFactorSecurityNotification => new RepeatedTwoFactorCodeFailuresNotification($site, $at));
    }

    public function appChanged(TwoFactorAppChanged $event): void
    {
        $this->send($event->userId, static fn (string $site, string $at): TwoFactorSecurityNotification => new TwoFactorAppChangedNotification($site, $at));
    }

    public function recoveryCodeUsed(RecoveryCodeUsed $event): void
    {
        $this->send($event->userId, static fn (string $site, string $at): TwoFactorSecurityNotification => new RecoveryCodeUsedNotification($site, $at, $event->remaining));
    }

    public function recoveryCodesRegenerated(RecoveryCodesRegenerated $event): void
    {
        $this->send($event->userId, static fn (string $site, string $at): TwoFactorSecurityNotification => new RecoveryCodesRegeneratedNotification($site, $at));
    }

    /**
     * @param  Closure(string, string): TwoFactorSecurityNotification  $notification
     */
    private function send(string $userId, Closure $notification): void
    {
        // Deactivated accounts too (the administration may reset one, and its owner is still told);
        // never an anonymized one, which has no address left to write to
        $user = UserModel::query()->withTrashed()->find($userId);

        if (! $user instanceof UserModel || $user->anonymized_at !== null) {
            return;
        }

        $user->notify($notification($this->branding->siteName(), now()->toIso8601String()));
    }
}
