<?php

declare(strict_types=1);

namespace App\Application\WebPush\DTOs;

/**
 * A browser's push subscription as it sends it (PushSubscription.toJSON()), already validated,
 * with the user agent that names the device.
 */
final readonly class WebPushSubscriptionInputDTO
{
    public function __construct(
        #[\SensitiveParameter]
        public string $endpoint,
        #[\SensitiveParameter]
        public string $p256dh,
        #[\SensitiveParameter]
        public string $auth,
        public string $userAgent,
    ) {}
}
