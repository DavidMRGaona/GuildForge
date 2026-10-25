<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Services;

use App\Application\Mail\DTOs\Response\QuotaStatusResponseDTO;
use App\Application\Mail\Services\EmailQuotaServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Mail\Enums\EmailStatus;
use App\Domain\Mail\Repositories\EmailLogRepositoryInterface;
use DateTimeImmutable;

final readonly class EmailQuotaService implements EmailQuotaServiceInterface
{
    public function __construct(
        private EmailLogRepositoryInterface $emailLogRepository,
        private SettingsServiceInterface $settingsService,
        private ?TimezoneServiceInterface $timezone = null,
    ) {}

    public function canSendEmail(): bool
    {
        $dailyLimit = (int) $this->settingsService->get('mail_quota_daily_limit', '0');
        $monthlyLimit = (int) $this->settingsService->get('mail_quota_monthly_limit', '0');

        if ($dailyLimit > 0) {
            $sentToday = $this->emailLogRepository->countByStatusSince(
                EmailStatus::Sent,
                $this->startOfDay(),
            );

            if ($sentToday >= $dailyLimit) {
                return false;
            }
        }

        if ($monthlyLimit > 0) {
            $sentThisMonth = $this->emailLogRepository->countByStatusSince(
                EmailStatus::Sent,
                $this->startOfMonth(),
            );

            if ($sentThisMonth >= $monthlyLimit) {
                return false;
            }
        }

        return true;
    }

    public function getQuotaStatus(): QuotaStatusResponseDTO
    {
        $dailyLimit = (int) $this->settingsService->get('mail_quota_daily_limit', '0');
        $monthlyLimit = (int) $this->settingsService->get('mail_quota_monthly_limit', '0');
        $warningThreshold = (int) $this->settingsService->get('mail_quota_warning_threshold', '80');

        $dailyUsed = $this->emailLogRepository->countByStatusSince(
            EmailStatus::Sent,
            $this->startOfDay(),
        );

        $monthlyUsed = $this->emailLogRepository->countByStatusSince(
            EmailStatus::Sent,
            $this->startOfMonth(),
        );

        $percentageUsed = $this->calculatePercentage($dailyUsed, $dailyLimit, $monthlyUsed, $monthlyLimit);
        $isLimitReached = ($dailyLimit > 0 && $dailyUsed >= $dailyLimit)
            || ($monthlyLimit > 0 && $monthlyUsed >= $monthlyLimit);
        $isWarning = ! $isLimitReached && $percentageUsed >= $warningThreshold;

        return new QuotaStatusResponseDTO(
            dailyUsed: $dailyUsed,
            dailyLimit: $dailyLimit,
            monthlyUsed: $monthlyUsed,
            monthlyLimit: $monthlyLimit,
            percentageUsed: $percentageUsed,
            isWarning: $isWarning,
            isLimitReached: $isLimitReached,
        );
    }

    private function calculatePercentage(int $dailyUsed, int $dailyLimit, int $monthlyUsed, int $monthlyLimit): float
    {
        $dailyPercentage = $dailyLimit > 0 ? ($dailyUsed / $dailyLimit) * 100 : 0.0;
        $monthlyPercentage = $monthlyLimit > 0 ? ($monthlyUsed / $monthlyLimit) * 100 : 0.0;

        return min(max($dailyPercentage, $monthlyPercentage), 100.0);
    }

    private function startOfDay(): DateTimeImmutable
    {
        $timezone = $this->timezone ?? app(TimezoneServiceInterface::class);

        return $timezone->displayDayRange($timezone->today())[0];
    }

    private function startOfMonth(): DateTimeImmutable
    {
        $timezone = $this->timezone ?? app(TimezoneServiceInterface::class);

        return $timezone->displayMonthRange($timezone->today())[0];
    }
}
