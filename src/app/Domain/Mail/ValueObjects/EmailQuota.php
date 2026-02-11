<?php

declare(strict_types=1);

namespace App\Domain\Mail\ValueObjects;

final readonly class EmailQuota
{
    public function __construct(
        public int $dailyLimit,
        public int $monthlyLimit,
        public int $warningThreshold,
    ) {}

    public function isOverDailyLimit(int $sent): bool
    {
        return $this->dailyLimit > 0 && $sent >= $this->dailyLimit;
    }

    public function isOverMonthlyLimit(int $sent): bool
    {
        return $this->monthlyLimit > 0 && $sent >= $this->monthlyLimit;
    }

    public function getUsagePercentage(int $sent, string $period): float
    {
        $limit = match ($period) {
            'daily' => $this->dailyLimit,
            'monthly' => $this->monthlyLimit,
            default => 0,
        };

        if ($limit <= 0) {
            return 0.0;
        }

        return min(100.0, ($sent / $limit) * 100);
    }

    public function shouldWarn(int $sent, string $period): bool
    {
        $percentage = $this->getUsagePercentage($sent, $period);

        return $percentage >= $this->warningThreshold;
    }
}
