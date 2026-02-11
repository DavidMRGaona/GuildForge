<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Services;

use App\Application\Mail\DTOs\Response\MailStatisticsResponseDTO;
use App\Application\Mail\Services\MailStatisticsServiceInterface;
use App\Domain\Mail\Enums\EmailStatus;
use App\Domain\Mail\Repositories\EmailLogRepositoryInterface;

final readonly class MailStatisticsService implements MailStatisticsServiceInterface
{
    public function __construct(
        private EmailLogRepositoryInterface $emailLogRepository,
    ) {}

    public function getStats(): MailStatisticsResponseDTO
    {
        $startOfDay = now()->startOfDay();
        $startOfMonth = now()->startOfMonth();

        $sentToday = $this->emailLogRepository->countByStatusSince(EmailStatus::Sent, $startOfDay);
        $sentThisMonth = $this->emailLogRepository->countByStatusSince(EmailStatus::Sent, $startOfMonth);
        $failedToday = $this->emailLogRepository->countByStatusSince(EmailStatus::Failed, $startOfDay);
        $failedThisMonth = $this->emailLogRepository->countByStatusSince(EmailStatus::Failed, $startOfMonth);

        $totalToday = $sentToday + $failedToday;
        $deliveryRate = $totalToday > 0
            ? round(($sentToday / $totalToday) * 100, 1)
            : 100.0;

        return new MailStatisticsResponseDTO(
            sentToday: $sentToday,
            sentThisMonth: $sentThisMonth,
            failedToday: $failedToday,
            failedThisMonth: $failedThisMonth,
            deliveryRate: $deliveryRate,
        );
    }
}
