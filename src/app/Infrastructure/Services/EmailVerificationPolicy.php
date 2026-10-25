<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Services\EmailVerificationPolicyInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

final readonly class EmailVerificationPolicy implements EmailVerificationPolicyInterface
{
    public function __construct(
        private SettingsServiceInterface $settings,
    ) {}

    public function mustVerify(object $user): bool
    {
        if (! $this->settings->isEmailVerificationRequired()) {
            return false;
        }

        if (! $user instanceof UserModel) {
            return true;
        }

        if ($user->email_verified_at !== null || $user->anonymized_at !== null) {
            return false;
        }

        $cutoff = $this->settings->getEmailVerificationEnforcedSince();

        if (! $cutoff instanceof \DateTimeImmutable || $user->created_at === null) {
            return true;
        }

        // Instants, not wall-clock times: created_at is in the app timezone, the cutoff in UTC
        return $user->created_at->getTimestamp() >= $cutoff->getTimestamp();
    }
}
