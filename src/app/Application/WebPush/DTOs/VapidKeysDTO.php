<?php

declare(strict_types=1);

namespace App\Application\WebPush\DTOs;

/**
 * The installation's VAPID identity (core#803): base64url keys and the contact push services see.
 */
final readonly class VapidKeysDTO
{
    public function __construct(
        public string $subject,
        public string $publicKey,
        #[\SensitiveParameter]
        public string $privateKey,
    ) {}
}
