<?php

declare(strict_types=1);

namespace App\Domain\Mail\Events;

final readonly class QuotaLimitReached
{
    public function __construct(
        public string $period,
    ) {}
}
