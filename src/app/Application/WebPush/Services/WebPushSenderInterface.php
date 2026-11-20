<?php

declare(strict_types=1);

namespace App\Application\WebPush\Services;

use App\Application\WebPush\DTOs\WebPushSubscriptionDTO;
use App\Domain\WebPush\WebPushDeliveryOutcome;

/**
 * Hands one encrypted message (aes128gcm, signed with the site's VAPID keys) to the browser's
 * push service (core#803). Logs failures with the push service's host and the status only.
 */
interface WebPushSenderInterface
{
    public function send(WebPushSubscriptionDTO $subscription, string $payload): WebPushDeliveryOutcome;
}
