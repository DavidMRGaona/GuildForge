<?php

declare(strict_types=1);

namespace App\Application\WebPush\DTOs;

use DateTimeImmutable;

/**
 * One browser of an account that receives push messages (core#803). The endpoint and the keys
 * identify the browser: never log them.
 */
final readonly class WebPushSubscriptionDTO
{
    /**
     * @param  string  $browser  `chrome`, `edge`, `firefox`, `safari`, `opera`, `samsung` or `other`.
     * @param  string  $platform  `android`, `ios`, `macos`, `windows`, `linux`, `chromeos` or `other`.
     */
    public function __construct(
        public string $id,
        public string $userId,
        public string $endpoint,
        #[\SensitiveParameter]
        public string $p256dh,
        #[\SensitiveParameter]
        public string $auth,
        public string $browser,
        public string $platform,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastUsedAt,
    ) {}
}
