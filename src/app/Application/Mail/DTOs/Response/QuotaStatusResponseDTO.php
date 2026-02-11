<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs\Response;

final readonly class QuotaStatusResponseDTO
{
    public function __construct(
        public int $dailyUsed,
        public int $dailyLimit,
        public int $monthlyUsed,
        public int $monthlyLimit,
        public float $percentageUsed,
        public bool $isWarning,
        public bool $isLimitReached,
    ) {}
}
