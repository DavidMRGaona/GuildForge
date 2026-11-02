<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Capabilities;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use App\Application\Services\SettingsServiceInterface;

final readonly class EmailVerificationRequiredCapability implements KnowledgeCapabilityInterface
{
    public function __construct(
        private SettingsServiceInterface $settings,
    ) {}

    public function key(): string
    {
        return 'core.email_verification_required';
    }

    public function passes(object $user): bool
    {
        return $this->settings->isEmailVerificationRequired();
    }
}
