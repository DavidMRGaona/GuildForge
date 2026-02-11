<?php

declare(strict_types=1);

namespace App\Domain\Mail\Events;

final readonly class SesComplaintReceived
{
    /**
     * @param  array<int, string>  $recipients
     */
    public function __construct(
        public string $messageId,
        public array $recipients,
    ) {}
}
