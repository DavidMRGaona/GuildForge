<?php

declare(strict_types=1);

namespace App\Domain\Mail\Events;

final readonly class SesBounceReceived
{
    /**
     * @param  array<int, string>  $recipients
     */
    public function __construct(
        public string $messageId,
        public string $bounceType,
        public array $recipients,
    ) {}
}
