<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush\Listeners;

use App\Application\WebPush\Services\WebPushPreferenceServiceInterface;
use App\Domain\Events\UserAnonymized;

/**
 * An anonymized account keeps no push choices (core#804). A deactivated one keeps them for when
 * it is restored: its browsers are already gone, so nothing is pushed meanwhile.
 */
final readonly class ForgetAccountWebPushPreferences
{
    public function __construct(
        private WebPushPreferenceServiceInterface $preferences,
    ) {}

    public function handle(UserAnonymized $event): void
    {
        $this->preferences->forgetUser($event->userId);
    }
}
