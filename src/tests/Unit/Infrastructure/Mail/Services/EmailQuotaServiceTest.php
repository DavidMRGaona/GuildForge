<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Mail\Services;

use App\Application\Mail\DTOs\Response\QuotaStatusResponseDTO;
use App\Application\Mail\Services\EmailQuotaServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Mail\Repositories\EmailLogRepositoryInterface;
use App\Infrastructure\Mail\Services\EmailQuotaService;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class EmailQuotaServiceTest extends TestCase
{
    private MockObject&EmailLogRepositoryInterface $emailLogRepository;

    private MockObject&SettingsServiceInterface $settingsService;

    private EmailQuotaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emailLogRepository = $this->createMock(EmailLogRepositoryInterface::class);
        $this->settingsService = $this->createMock(SettingsServiceInterface::class);

        $this->service = new EmailQuotaService(
            $this->emailLogRepository,
            $this->settingsService,
        );
    }

    public function test_it_implements_email_quota_service_interface(): void
    {
        $this->assertInstanceOf(EmailQuotaServiceInterface::class, $this->service);
    }

    public function test_can_send_email_returns_true_when_no_limits_set(): void
    {
        $this->settingsService->method('get')
            ->willReturnMap([
                ['mail_quota_daily_limit', '0', '0'],
                ['mail_quota_monthly_limit', '0', '0'],
                ['mail_quota_warning_threshold', '80', '80'],
            ]);

        $this->emailLogRepository->expects($this->never())
            ->method('countByStatusSince');

        $result = $this->service->canSendEmail();

        $this->assertTrue($result);
    }

    public function test_can_send_email_returns_true_when_under_daily_limit(): void
    {
        $this->settingsService->method('get')
            ->willReturnMap([
                ['mail_quota_daily_limit', '0', '100'],
                ['mail_quota_monthly_limit', '0', '0'],
                ['mail_quota_warning_threshold', '80', '80'],
            ]);

        $this->emailLogRepository->method('countByStatusSince')
            ->willReturn(50);

        $result = $this->service->canSendEmail();

        $this->assertTrue($result);
    }

    public function test_can_send_email_returns_false_when_daily_limit_reached(): void
    {
        $this->settingsService->method('get')
            ->willReturnMap([
                ['mail_quota_daily_limit', '0', '100'],
                ['mail_quota_monthly_limit', '0', '0'],
                ['mail_quota_warning_threshold', '80', '80'],
            ]);

        $this->emailLogRepository->method('countByStatusSince')
            ->willReturn(100);

        $result = $this->service->canSendEmail();

        $this->assertFalse($result);
    }

    public function test_can_send_email_returns_false_when_monthly_limit_reached(): void
    {
        $this->settingsService->method('get')
            ->willReturnMap([
                ['mail_quota_daily_limit', '0', '0'],
                ['mail_quota_monthly_limit', '0', '1000'],
                ['mail_quota_warning_threshold', '80', '80'],
            ]);

        $this->emailLogRepository->method('countByStatusSince')
            ->willReturn(1000);

        $result = $this->service->canSendEmail();

        $this->assertFalse($result);
    }

    public function test_get_quota_status_returns_correct_dto(): void
    {
        $this->settingsService->method('get')
            ->willReturnMap([
                ['mail_quota_daily_limit', '0', '100'],
                ['mail_quota_monthly_limit', '0', '1000'],
                ['mail_quota_warning_threshold', '80', '80'],
            ]);

        $this->emailLogRepository->method('countByStatusSince')
            ->willReturnOnConsecutiveCalls(25, 200);

        $result = $this->service->getQuotaStatus();

        $this->assertInstanceOf(QuotaStatusResponseDTO::class, $result);
        $this->assertSame(25, $result->dailyUsed);
        $this->assertSame(100, $result->dailyLimit);
        $this->assertSame(200, $result->monthlyUsed);
        $this->assertSame(1000, $result->monthlyLimit);
        $this->assertSame(25.0, $result->percentageUsed);
        $this->assertFalse($result->isWarning);
        $this->assertFalse($result->isLimitReached);
    }

    public function test_get_quota_status_detects_warning_threshold(): void
    {
        $this->settingsService->method('get')
            ->willReturnMap([
                ['mail_quota_daily_limit', '0', '100'],
                ['mail_quota_monthly_limit', '0', '1000'],
                ['mail_quota_warning_threshold', '80', '80'],
            ]);

        $this->emailLogRepository->method('countByStatusSince')
            ->willReturnOnConsecutiveCalls(85, 500);

        $result = $this->service->getQuotaStatus();

        $this->assertInstanceOf(QuotaStatusResponseDTO::class, $result);
        $this->assertSame(85, $result->dailyUsed);
        $this->assertSame(100, $result->dailyLimit);
        $this->assertTrue($result->isWarning);
        $this->assertFalse($result->isLimitReached);
    }

    public function test_can_send_email_returns_false_when_daily_limit_exceeded(): void
    {
        $this->settingsService->method('get')
            ->willReturnMap([
                ['mail_quota_daily_limit', '0', '100'],
                ['mail_quota_monthly_limit', '0', '0'],
                ['mail_quota_warning_threshold', '80', '80'],
            ]);

        $this->emailLogRepository->method('countByStatusSince')
            ->willReturn(150);

        $result = $this->service->canSendEmail();

        $this->assertFalse($result);
    }

    public function test_can_send_email_checks_both_limits_when_both_set(): void
    {
        $this->settingsService->method('get')
            ->willReturnMap([
                ['mail_quota_daily_limit', '0', '100'],
                ['mail_quota_monthly_limit', '0', '1000'],
                ['mail_quota_warning_threshold', '80', '80'],
            ]);

        $this->emailLogRepository->method('countByStatusSince')
            ->willReturn(50);

        $result = $this->service->canSendEmail();

        $this->assertTrue($result);
    }

    public function test_get_quota_status_marks_limit_reached_when_daily_limit_hit(): void
    {
        $this->settingsService->method('get')
            ->willReturnMap([
                ['mail_quota_daily_limit', '0', '100'],
                ['mail_quota_monthly_limit', '0', '1000'],
                ['mail_quota_warning_threshold', '80', '80'],
            ]);

        $this->emailLogRepository->method('countByStatusSince')
            ->willReturnOnConsecutiveCalls(100, 500);

        $result = $this->service->getQuotaStatus();

        $this->assertTrue($result->isLimitReached);
        $this->assertFalse($result->isWarning);
    }

    public function test_get_quota_status_uses_higher_percentage_of_daily_and_monthly(): void
    {
        $this->settingsService->method('get')
            ->willReturnMap([
                ['mail_quota_daily_limit', '0', '100'],
                ['mail_quota_monthly_limit', '0', '1000'],
                ['mail_quota_warning_threshold', '80', '80'],
            ]);

        $this->emailLogRepository->method('countByStatusSince')
            ->willReturnOnConsecutiveCalls(60, 100);

        $result = $this->service->getQuotaStatus();

        $this->assertSame(60.0, $result->percentageUsed);
    }

    public function test_get_quota_status_returns_zero_percentage_when_no_limits_set(): void
    {
        $this->settingsService->method('get')
            ->willReturnMap([
                ['mail_quota_daily_limit', '0', '0'],
                ['mail_quota_monthly_limit', '0', '0'],
                ['mail_quota_warning_threshold', '80', '80'],
            ]);

        $this->emailLogRepository->method('countByStatusSince')
            ->willReturnOnConsecutiveCalls(50, 200);

        $result = $this->service->getQuotaStatus();

        $this->assertSame(0.0, $result->percentageUsed);
        $this->assertFalse($result->isWarning);
        $this->assertFalse($result->isLimitReached);
    }

    public function test_get_quota_status_caps_percentage_at_100(): void
    {
        $this->settingsService->method('get')
            ->willReturnMap([
                ['mail_quota_daily_limit', '0', '100'],
                ['mail_quota_monthly_limit', '0', '1000'],
                ['mail_quota_warning_threshold', '80', '80'],
            ]);

        $this->emailLogRepository->method('countByStatusSince')
            ->willReturnOnConsecutiveCalls(150, 500);

        $result = $this->service->getQuotaStatus();

        $this->assertSame(100.0, $result->percentageUsed);
    }
}
