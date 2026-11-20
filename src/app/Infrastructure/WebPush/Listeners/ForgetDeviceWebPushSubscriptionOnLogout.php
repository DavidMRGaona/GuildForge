<?php

declare(strict_types=1);

namespace App\Infrastructure\WebPush\Listeners;

use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Signing out forgets this browser's subscription (core#803), even when the page could not send
 * its own request first: the session remembers the subscription this browser made. Only one of
 * the account itself, and a failure never stops the sign-out.
 */
final readonly class ForgetDeviceWebPushSubscriptionOnLogout
{
    public function __construct(
        private WebPushSubscriptionServiceInterface $subscriptions,
    ) {}

    public function handle(Logout $event): void
    {
        if ($event->user === null || ! app()->bound('session.store')) {
            return;
        }

        try {
            $subscriptionId = session()->pull(WebPushSubscriptionServiceInterface::SESSION_KEY);

            if (is_string($subscriptionId) && $subscriptionId !== '') {
                $this->subscriptions->forgetForUser((string) $event->user->getAuthIdentifier(), $subscriptionId);
            }
        } catch (Throwable $exception) {
            Log::warning('The push subscription of a browser signing out could not be forgotten', [
                'exception' => $exception::class,
            ]);
        }
    }
}
