<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail\ValueObjects;

use App\Domain\Mail\ValueObjects\EmailQuota;
use PHPUnit\Framework\TestCase;

final class EmailQuotaTest extends TestCase
{
    public function test_it_creates_quota_with_readonly_properties(): void
    {
        $quota = new EmailQuota(
            dailyLimit: 100,
            monthlyLimit: 3000,
            warningThreshold: 80,
        );

        $this->assertEquals(100, $quota->dailyLimit);
        $this->assertEquals(3000, $quota->monthlyLimit);
        $this->assertEquals(80, $quota->warningThreshold);
    }

    public function test_is_over_daily_limit_returns_true_when_sent_equals_limit(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertTrue($quota->isOverDailyLimit(100));
    }

    public function test_is_over_daily_limit_returns_true_when_sent_exceeds_limit(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertTrue($quota->isOverDailyLimit(150));
    }

    public function test_is_over_daily_limit_returns_false_when_sent_below_limit(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertFalse($quota->isOverDailyLimit(99));
    }

    public function test_is_over_daily_limit_returns_false_when_limit_is_zero(): void
    {
        $quota = new EmailQuota(dailyLimit: 0, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertFalse($quota->isOverDailyLimit(50));
    }

    public function test_is_over_monthly_limit_returns_true_when_sent_equals_limit(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertTrue($quota->isOverMonthlyLimit(3000));
    }

    public function test_is_over_monthly_limit_returns_true_when_sent_exceeds_limit(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertTrue($quota->isOverMonthlyLimit(3500));
    }

    public function test_is_over_monthly_limit_returns_false_when_sent_below_limit(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertFalse($quota->isOverMonthlyLimit(2999));
    }

    public function test_is_over_monthly_limit_returns_false_when_limit_is_zero(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 0, warningThreshold: 80);

        $this->assertFalse($quota->isOverMonthlyLimit(50));
    }

    public function test_get_usage_percentage_returns_correct_daily_percentage(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertEquals(50.0, $quota->getUsagePercentage(50, 'daily'));
    }

    public function test_get_usage_percentage_returns_correct_monthly_percentage(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertEquals(50.0, $quota->getUsagePercentage(1500, 'monthly'));
    }

    public function test_get_usage_percentage_caps_at_100(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertEquals(100.0, $quota->getUsagePercentage(200, 'daily'));
    }

    public function test_get_usage_percentage_returns_zero_for_zero_limit(): void
    {
        $quota = new EmailQuota(dailyLimit: 0, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertEquals(0.0, $quota->getUsagePercentage(50, 'daily'));
    }

    public function test_get_usage_percentage_returns_zero_for_invalid_period(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertEquals(0.0, $quota->getUsagePercentage(50, 'weekly'));
    }

    public function test_should_warn_returns_true_when_usage_at_threshold(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertTrue($quota->shouldWarn(80, 'daily'));
    }

    public function test_should_warn_returns_true_when_usage_above_threshold(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertTrue($quota->shouldWarn(90, 'daily'));
    }

    public function test_should_warn_returns_false_when_usage_below_threshold(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertFalse($quota->shouldWarn(79, 'daily'));
    }

    public function test_should_warn_works_with_monthly_period(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertTrue($quota->shouldWarn(2400, 'monthly'));
    }

    public function test_should_warn_returns_false_for_monthly_below_threshold(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertFalse($quota->shouldWarn(2399, 'monthly'));
    }

    public function test_get_usage_percentage_returns_zero_for_zero_sent(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertEquals(0.0, $quota->getUsagePercentage(0, 'daily'));
    }

    public function test_get_usage_percentage_returns_100_at_exact_limit(): void
    {
        $quota = new EmailQuota(dailyLimit: 100, monthlyLimit: 3000, warningThreshold: 80);

        $this->assertEquals(100.0, $quota->getUsagePercentage(100, 'daily'));
    }
}
