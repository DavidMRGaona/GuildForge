<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services;

use App\Infrastructure\Services\TimezoneService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TimezoneServiceTest extends TestCase
{
    private string $previousZone;

    private TimezoneService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousZone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $this->service = new TimezoneService('Europe/Madrid');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        date_default_timezone_set($this->previousZone);

        parent::tearDown();
    }

    public function test_a_display_wall_time_is_read_as_the_instant_it_names(): void
    {
        $this->assertSame('2026-10-25T02:30:00+00:00', $this->service->fromDisplayWallTime('2026-10-25 03:30')->format('c'));
        $this->assertSame('2026-07-01T10:00:00+00:00', $this->service->fromDisplayWallTime('2026-07-01T12:00')->format('c'));
        $this->assertSame('2026-07-01T10:00:30+00:00', $this->service->fromDisplayWallTime('2026-07-01T12:00:30')->format('c'));
    }

    public function test_wall_times_in_the_changeover_hours_follow_php(): void
    {
        // Repeated hour: second pass (CET); skipped hour: moved forward to CEST
        $this->assertSame('2026-10-25T01:30:00+00:00', $this->service->fromDisplayWallTime('2026-10-25 02:30')->format('c'));
        $this->assertSame('2026-03-29T01:30:00+00:00', $this->service->fromDisplayWallTime('2026-03-29 02:30')->format('c'));
    }

    public function test_a_malformed_wall_time_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->fromDisplayWallTime('25/10/2026 03:30');
    }

    public function test_an_impossible_wall_time_is_rejected_instead_of_rolling_over(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->fromDisplayWallTime('2026-02-30 10:00');
    }

    public function test_the_long_and_short_days_have_their_real_length(): void
    {
        [$start, $end] = $this->service->displayDayRange('2026-10-25');
        $this->assertSame(['2026-10-24T22:00:00+00:00', '2026-10-25T23:00:00+00:00'], [$start->format('c'), $end->format('c')]);

        [$start, $end] = $this->service->displayDayRange('2026-03-29');
        $this->assertSame(['2026-03-28T23:00:00+00:00', '2026-03-29T22:00:00+00:00'], [$start->format('c'), $end->format('c')]);
    }

    public function test_the_month_range_spans_display_months(): void
    {
        [$start, $end] = $this->service->displayMonthRange(new DateTimeImmutable('2026-10-15T12:00:00Z'));
        $this->assertSame(['2026-09-30T22:00:00+00:00', '2026-10-31T23:00:00+00:00'], [$start->format('c'), $end->format('c')]);
    }

    public function test_instants_are_formatted_and_prefilled_in_the_display_zone(): void
    {
        $instant = new DateTimeImmutable('2026-10-25T00:15:00Z');
        $this->assertSame('25/10/2026 02:15', $this->service->format($instant));
        $this->assertSame('2026-10-25T02:15', $this->service->toDisplayWallTime($instant));
    }

    public function test_today_is_the_display_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-24T22:30:00Z')); // 00:30 in Madrid
        $this->assertSame('2026-10-25T00:00:00+02:00', $this->service->today()->format('c'));
    }

    public function test_conversions_use_the_zones_in_force(): void
    {
        $instant = new DateTimeImmutable('2026-07-01T10:00:00Z');
        $this->assertSame('2026-07-01T12:00:00+02:00', $this->service->toDisplay($instant)->format('c'));
        $this->assertSame('UTC', $this->service->toStorage($instant)->getTimezone()->getName());

        date_default_timezone_set('Europe/Madrid');
        $this->assertSame('Europe/Madrid', $this->service->storageTimezone()->getName());
    }

    public function test_loosely_written_wall_times_are_rejected(): void
    {
        foreach (['2026-7-1 5:00', '2026-07-01  12:00', '2026-07-01 12:00 '] as $wallTime) {
            try {
                $this->service->fromDisplayWallTime($wallTime);
                $this->fail("Accepted \"{$wallTime}\"");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_day_range_takes_an_instant_and_uses_its_display_day(): void
    {
        // 00:30 on 25 October in Madrid, still the 24th in UTC
        [$start, $end] = $this->service->displayDayRange(new DateTimeImmutable('2026-10-24T22:30:00Z'));
        $this->assertSame(['2026-10-24T22:00:00+00:00', '2026-10-25T23:00:00+00:00'], [$start->format('c'), $end->format('c')]);
    }

    public function test_an_invalid_day_string_is_rejected(): void
    {
        foreach (['2026-02-30', '25/10/2026', '2026-10-25 10:00', ''] as $day) {
            try {
                $this->service->displayDayRange($day);
                $this->fail("Accepted \"{$day}\"");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_march_month_range_ends_when_april_starts_in_the_display_zone(): void
    {
        [$start, $end] = $this->service->displayMonthRange(new DateTimeImmutable('2026-03-15T12:00:00Z'));
        $this->assertSame(['2026-02-28T23:00:00+00:00', '2026-03-31T22:00:00+00:00'], [$start->format('c'), $end->format('c')]);
    }

    public function test_a_custom_format_is_applied_in_the_display_zone(): void
    {
        $this->assertSame('25/10/2026 02:15', $this->service->toDisplayWallTime(new DateTimeImmutable('2026-10-25T00:15:00Z'), 'd/m/Y H:i'));
    }

    public function test_iso_is_null_for_null_and_carries_the_display_offset(): void
    {
        $this->assertNull($this->service->iso(null));
        $this->assertSame('2026-07-01T12:00:00+02:00', $this->service->iso(new DateTimeImmutable('2026-07-01T10:00:00Z')));
        $this->assertSame('2026-01-01T11:00:00+01:00', $this->service->iso(new DateTimeImmutable('2026-01-01T10:00:00Z')));
    }
}
