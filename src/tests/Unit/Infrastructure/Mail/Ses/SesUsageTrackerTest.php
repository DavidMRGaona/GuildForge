<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Mail\Ses;

use App\Infrastructure\Mail\Ses\SesUsageTracker;
use App\Infrastructure\Persistence\Eloquent\Models\SesUsageRecordModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SesUsageTracker::class)]
final class SesUsageTrackerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SesUsageTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tracker = new SesUsageTracker;
    }

    public function test_increment_emails_sent_creates_record_for_today(): void
    {
        $this->tracker->incrementEmailsSent();

        $this->assertDatabaseCount('ses_usage_records', 1);

        $record = SesUsageRecordModel::query()->first();
        $this->assertNotNull($record);
        $this->assertSame(1, $record->emails_sent);
        $this->assertTrue(Carbon::today()->isSameDay($record->date));
    }

    public function test_increment_emails_sent_updates_existing_record(): void
    {
        SesUsageRecordModel::create([
            'date' => Carbon::today(),
            'emails_sent' => 5,
            'estimated_cost' => 0.0005,
            'bounces_count' => 0,
            'complaints_count' => 0,
        ]);

        $this->tracker->incrementEmailsSent(3);

        $this->assertDatabaseCount('ses_usage_records', 1);

        $record = SesUsageRecordModel::query()->first();
        $this->assertNotNull($record);
        $this->assertSame(8, $record->emails_sent);
    }

    public function test_increment_emails_sent_calculates_estimated_cost(): void
    {
        $this->tracker->incrementEmailsSent(1000);

        $record = SesUsageRecordModel::query()->whereDate('date', Carbon::today())->first();

        $this->assertNotNull($record);
        $this->assertSame(1000, $record->emails_sent);
        $this->assertEqualsWithDelta(0.10, $record->estimated_cost, 0.0001);
    }

    public function test_increment_bounces_creates_record_for_today(): void
    {
        $this->tracker->incrementBounces();

        $this->assertDatabaseCount('ses_usage_records', 1);

        $record = SesUsageRecordModel::query()->first();
        $this->assertNotNull($record);
        $this->assertSame(1, $record->bounces_count);
    }

    public function test_increment_bounces_updates_existing_record(): void
    {
        SesUsageRecordModel::create([
            'date' => Carbon::today(),
            'emails_sent' => 0,
            'estimated_cost' => 0,
            'bounces_count' => 2,
            'complaints_count' => 0,
        ]);

        $this->tracker->incrementBounces();

        $this->assertDatabaseCount('ses_usage_records', 1);

        $record = SesUsageRecordModel::query()->first();
        $this->assertNotNull($record);
        $this->assertSame(3, $record->bounces_count);
    }

    public function test_increment_complaints(): void
    {
        $this->tracker->incrementComplaints();

        $this->assertDatabaseCount('ses_usage_records', 1);

        $record = SesUsageRecordModel::query()->first();
        $this->assertNotNull($record);
        $this->assertSame(1, $record->complaints_count);
    }

    public function test_get_today_usage_returns_existing_record(): void
    {
        SesUsageRecordModel::create([
            'date' => Carbon::today(),
            'emails_sent' => 42,
            'estimated_cost' => 0.0042,
            'bounces_count' => 3,
            'complaints_count' => 1,
        ]);

        $result = $this->tracker->getTodayUsage();

        $this->assertInstanceOf(SesUsageRecordModel::class, $result);
        $this->assertSame(42, $result->emails_sent);
        $this->assertSame(3, $result->bounces_count);
        $this->assertSame(1, $result->complaints_count);
    }

    public function test_get_today_usage_returns_default_when_no_record(): void
    {
        $result = $this->tracker->getTodayUsage();

        $this->assertInstanceOf(SesUsageRecordModel::class, $result);
        $this->assertSame(0, $result->emails_sent);
        $this->assertSame(0, $result->bounces_count);
        $this->assertSame(0, $result->complaints_count);
        $this->assertEqualsWithDelta(0.0, $result->estimated_cost, 0.0001);
    }

    public function test_get_monthly_usage_aggregates_current_month(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15));

        SesUsageRecordModel::create([
            'date' => '2025-06-01',
            'emails_sent' => 100,
            'estimated_cost' => 0.01,
            'bounces_count' => 2,
            'complaints_count' => 1,
        ]);

        SesUsageRecordModel::create([
            'date' => '2025-06-10',
            'emails_sent' => 200,
            'estimated_cost' => 0.02,
            'bounces_count' => 3,
            'complaints_count' => 0,
        ]);

        SesUsageRecordModel::create([
            'date' => '2025-06-15',
            'emails_sent' => 50,
            'estimated_cost' => 0.005,
            'bounces_count' => 1,
            'complaints_count' => 2,
        ]);

        $result = $this->tracker->getMonthlyUsage();

        $this->assertIsArray($result);
        $this->assertSame(350, $result['emails_sent']);
        $this->assertEqualsWithDelta(0.035, $result['estimated_cost'], 0.0001);
        $this->assertSame(6, $result['bounces_count']);
        $this->assertSame(3, $result['complaints_count']);

        Carbon::setTestNow();
    }

    public function test_get_monthly_usage_excludes_other_months(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15));

        // Last month - should be excluded
        SesUsageRecordModel::create([
            'date' => '2025-05-28',
            'emails_sent' => 500,
            'estimated_cost' => 0.05,
            'bounces_count' => 10,
            'complaints_count' => 5,
        ]);

        // This month - should be included
        SesUsageRecordModel::create([
            'date' => '2025-06-05',
            'emails_sent' => 150,
            'estimated_cost' => 0.015,
            'bounces_count' => 1,
            'complaints_count' => 0,
        ]);

        $result = $this->tracker->getMonthlyUsage();

        $this->assertIsArray($result);
        $this->assertSame(150, $result['emails_sent']);
        $this->assertEqualsWithDelta(0.015, $result['estimated_cost'], 0.0001);
        $this->assertSame(1, $result['bounces_count']);
        $this->assertSame(0, $result['complaints_count']);

        Carbon::setTestNow();
    }

    public function test_get_estimated_cost_calculation(): void
    {
        $this->assertEqualsWithDelta(0.05, $this->tracker->getEstimatedCost(500), 0.0001);
        $this->assertEqualsWithDelta(0.25, $this->tracker->getEstimatedCost(2500), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->tracker->getEstimatedCost(0), 0.0001);
    }
}
