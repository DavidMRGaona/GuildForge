<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Mail\Services;

use App\Domain\Mail\Enums\EmailStatus;
use App\Domain\Mail\Repositories\EmailLogRepositoryInterface;
use App\Infrastructure\Mail\Services\MailStatisticsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

#[CoversClass(MailStatisticsService::class)]
final class MailStatisticsServiceTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    private EmailLogRepositoryInterface&MockObject $repository;

    private MailStatisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(EmailLogRepositoryInterface::class);
        $this->service = new MailStatisticsService($this->repository);
    }

    public function test_the_day_and_month_bounds_are_display_zone_midnights_under_utc_storage(): void
    {
        $this->useUtcStorage();
        Carbon::setTestNow(Carbon::parse('2026-10-31T23:30:00Z'));

        $bounds = [];
        $this->repository
            ->expects($this->atLeastOnce())
            ->method('countByStatusSince')
            ->willReturnCallback(function (EmailStatus $status, Carbon $since) use (&$bounds): int {
                $bounds[$since->getTimestamp()] = true;

                return 0;
            });

        $this->service->getStats();

        // Madrid is on CET (+01:00) since 25 Oct, so 1 Nov 00:00 there is 2026-10-31T23:00:00Z, day and month alike
        $this->assertSame([Carbon::parse('2026-10-31T23:00:00Z')->getTimestamp()], array_keys($bounds));

        Carbon::setTestNow();
    }

    public function test_get_stats_returns_correct_daily_counts(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15, 14, 30, 0));

        $this->repository
            ->expects($this->exactly(4))
            ->method('countByStatusSince')
            ->willReturnCallback(function (EmailStatus $status, Carbon $since): int {
                if ($status === EmailStatus::Sent && $since->equalTo(Carbon::now(config('app.display_timezone'))->startOfDay())) {
                    return 25;
                }
                if ($status === EmailStatus::Failed && $since->equalTo(Carbon::now(config('app.display_timezone'))->startOfDay())) {
                    return 3;
                }

                return 0;
            });

        $result = $this->service->getStats();

        $this->assertSame(25, $result->sentToday);
        $this->assertSame(3, $result->failedToday);

        Carbon::setTestNow();
    }

    public function test_get_stats_returns_correct_monthly_counts(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15, 14, 30, 0));

        $this->repository
            ->expects($this->exactly(4))
            ->method('countByStatusSince')
            ->willReturnCallback(function (EmailStatus $status, Carbon $since): int {
                $startOfMonth = Carbon::now(config('app.display_timezone'))->startOfMonth();

                if ($status === EmailStatus::Sent && $since->equalTo($startOfMonth)) {
                    return 450;
                }
                if ($status === EmailStatus::Failed && $since->equalTo($startOfMonth)) {
                    return 12;
                }

                return 0;
            });

        $result = $this->service->getStats();

        $this->assertSame(450, $result->sentThisMonth);
        $this->assertSame(12, $result->failedThisMonth);

        Carbon::setTestNow();
    }

    public function test_get_stats_calculates_delivery_rate(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15, 14, 30, 0));

        $this->repository
            ->expects($this->exactly(4))
            ->method('countByStatusSince')
            ->willReturnCallback(function (EmailStatus $status, Carbon $since): int {
                $startOfMonth = Carbon::now(config('app.display_timezone'))->startOfMonth();

                if ($status === EmailStatus::Sent && $since->equalTo($startOfMonth)) {
                    return 90;
                }
                if ($status === EmailStatus::Failed && $since->equalTo($startOfMonth)) {
                    return 10;
                }

                // Daily counts
                if ($status === EmailStatus::Sent) {
                    return 9;
                }
                if ($status === EmailStatus::Failed) {
                    return 1;
                }

                return 0;
            });

        $result = $this->service->getStats();

        $this->assertSame(90.0, $result->deliveryRate);

        Carbon::setTestNow();
    }

    public function test_get_stats_handles_zero_emails(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15, 14, 30, 0));

        $this->repository
            ->expects($this->exactly(4))
            ->method('countByStatusSince')
            ->willReturn(0);

        $result = $this->service->getStats();

        $this->assertSame(0, $result->sentToday);
        $this->assertSame(0, $result->sentThisMonth);
        $this->assertSame(0, $result->failedToday);
        $this->assertSame(0, $result->failedThisMonth);
        $this->assertSame(100.0, $result->deliveryRate);

        Carbon::setTestNow();
    }
}
