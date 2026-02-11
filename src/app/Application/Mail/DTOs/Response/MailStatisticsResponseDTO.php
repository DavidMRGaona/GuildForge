<?php

declare(strict_types=1);

namespace App\Application\Mail\DTOs\Response;

final readonly class MailStatisticsResponseDTO
{
    public function __construct(
        public int $sentToday,
        public int $sentThisMonth,
        public int $failedToday,
        public int $failedThisMonth,
        public float $deliveryRate,
    ) {}
}
