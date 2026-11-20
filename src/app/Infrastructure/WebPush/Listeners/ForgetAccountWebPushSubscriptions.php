<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush\Listeners;

use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Domain\Events\UserAnonymized;
use App\Domain\Events\UserDeactivated;

/**
 * A deactivated or anonymized account is nobody now: none of its browsers gets another message
 * (core#803). Deleting the account itself takes them along (foreign key).
 */
final readonly class ForgetAccountWebPushSubscriptions
{
    public function __construct(
        private WebPushSubscriptionServiceInterface $subscriptions,
    ) {}

    public function handle(UserDeactivated|UserAnonymized $event): void
    {
        $this->subscriptions->forgetUser($event->userId);
    }
}
