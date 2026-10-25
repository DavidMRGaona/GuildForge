<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

/**
 * The calendar feed is the boundary the frontend groups entries on, so the
 * serialized offset decides which calendar day an entry lands in.
 */
final class CalendarTimezoneTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    public function test_entries_carry_the_summer_time_offset(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Evento de verano',
            'start_date' => new DateTimeImmutable('2025-07-15 18:00:00', new DateTimeZone('Europe/Madrid')),
            'end_date' => new DateTimeImmutable('2025-07-15 21:00:00', new DateTimeZone('Europe/Madrid')),
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-07-01&end=2025-07-31');

        $response->assertStatus(200);
        $response->assertJsonFragment(['start' => '2025-07-15T18:00:00+02:00']);
    }

    public function test_entries_carry_the_winter_time_offset(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Evento de invierno',
            'start_date' => new DateTimeImmutable('2025-01-15 18:00:00', new DateTimeZone('Europe/Madrid')),
            'end_date' => new DateTimeImmutable('2025-01-15 21:00:00', new DateTimeZone('Europe/Madrid')),
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $response->assertJsonFragment(['start' => '2025-01-15T18:00:00+01:00']);
    }

    public function test_early_morning_entry_keeps_the_venue_day(): void
    {
        // 00:30 in Madrid is still the previous day in UTC, so an entry serialized
        // without the venue offset would be grouped under 14 July by the frontend.
        EventModel::factory()->published()->create([
            'title' => 'Partida nocturna',
            'start_date' => new DateTimeImmutable('2025-07-15 00:30:00', new DateTimeZone('Europe/Madrid')),
            'end_date' => new DateTimeImmutable('2025-07-15 03:00:00', new DateTimeZone('Europe/Madrid')),
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-07-01&end=2025-07-31');

        $response->assertStatus(200);
        $response->assertJsonFragment(['start' => '2025-07-15T00:30:00+02:00']);
    }

    public function test_a_range_without_offset_is_read_in_the_display_zone_under_utc_storage(): void
    {
        // FullCalendar with a named time zone sends wall times without offset
        $this->useUtcStorage();
        $range = $this->recordRequestedRange();

        $this->getJson('/eventos/calendario?start=2026-06-29T00:00:00&end=2026-07-06T00:00:00')->assertStatus(200);

        $this->assertSame('2026-06-28T22:00:00+00:00', $this->utc($range->from));
        $this->assertSame('2026-07-05T22:00:00+00:00', $this->utc($range->to));
    }

    public function test_an_explicit_offset_in_the_range_wins_under_utc_storage(): void
    {
        $this->useUtcStorage();
        $range = $this->recordRequestedRange();

        $this->getJson('/eventos/calendario?'.http_build_query([
            'start' => '2026-06-29T00:00:00+00:00',
            'end' => '2026-07-06T00:00:00+00:00',
        ]))->assertStatus(200);

        $this->assertSame('2026-06-29T00:00:00+00:00', $this->utc($range->from));
        $this->assertSame('2026-07-06T00:00:00+00:00', $this->utc($range->to));
    }

    /** @return object{from: ?DateTimeImmutable, to: ?DateTimeImmutable} */
    private function recordRequestedRange(): object
    {
        $range = new class
        {
            public ?DateTimeImmutable $from = null;

            public ?DateTimeImmutable $to = null;
        };

        $aggregator = Mockery::mock(CalendarAggregatorServiceInterface::class);
        $aggregator->shouldReceive('findByDateRange')->once()->andReturnUsing(
            function (DateTimeImmutable $from, DateTimeImmutable $to) use ($range): array {
                $range->from = $from;
                $range->to = $to;

                return [];
            },
        );
        $this->app->instance(CalendarAggregatorServiceInterface::class, $aggregator);

        return $range;
    }

    private function utc(?DateTimeImmutable $instant): ?string
    {
        return $instant?->setTimezone(new DateTimeZone('UTC'))->format('c');
    }
}
