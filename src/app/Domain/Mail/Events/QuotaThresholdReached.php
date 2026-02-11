<?php

declare(strict_types=1);

namespace App\Domain\Mail\Events;

final readonly class QuotaThresholdReached
{
    public function __construct(
        public int $percentage,
        public string $period,
    ) {}
}
