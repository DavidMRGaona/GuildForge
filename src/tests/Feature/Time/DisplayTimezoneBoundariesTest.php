<?php

declare(strict_types=1);

namespace Tests\Feature\Time;

use App\Application\Mail\Services\EmailQuotaServiceInterface;
use App\Application\Seo\DTOs\SeoScheduleDTO;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Mail\Ses\SesUsageTracker;
use App\Infrastructure\Persistence\Eloquent\Models\EmailLogModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\SesUsageRecordModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentEventRepository;
use App\Infrastructure\Seo\SeoDescriptionBuilder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

/**
 * What people read and the day a query counts are decided in the display zone,
 * even when the instants are stored in UTC.
 */
final class DisplayTimezoneBoundariesTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useUtcStorage();
    }

    public function test_the_seo_description_shows_the_display_time(): void
    {
        app()->setLocale('es');

        $description = app(SeoDescriptionBuilder::class)->build(
            'Texto',
            new SeoScheduleDTO(start: new DateTimeImmutable('2026-07-01T10:00:00Z')),
            '',
        );

        $this->assertStringContainsString('12:00', $description);
    }

    public function test_an_event_late_in_the_display_day_is_in_that_day_of_the_calendar(): void
    {
        // 2026-10-31 23:30 in Madrid.
        EventModel::factory()->published()->create([
            'title' => 'Partida tardía',
            'start_date' => new DateTimeImmutable('2026-10-31T22:30:00Z'),
            'end_date' => new DateTimeImmutable('2026-10-31T22:45:00Z'),
        ]);

        $this->getJson('/eventos/calendario?start=2026-10-31&end=2026-11-01')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Partida tardía']);

        $this->getJson('/eventos/calendario?start=2026-11-01&end=2026-11-02')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Partida tardía']);
    }

    public function test_an_event_just_after_display_midnight_is_in_the_next_day_of_the_calendar(): void
    {
        // 2026-11-01 00:30 in Madrid, still 31 October in UTC.
        EventModel::factory()->published()->create([
            'title' => 'Partida de madrugada',
            'start_date' => new DateTimeImmutable('2026-10-31T23:30:00Z'),
            'end_date' => new DateTimeImmutable('2026-10-31T23:45:00Z'),
        ]);

        $this->getJson('/eventos/calendario?start=2026-10-31&end=2026-11-01')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Partida de madrugada']);

        $this->getJson('/eventos/calendario?start=2026-11-01&end=2026-11-02')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Partida de madrugada']);
    }

    public function test_an_event_starting_exactly_at_the_end_day_midnight_is_outside_the_calendar_range(): void
    {
        // 2026-11-01 00:00 in Madrid: the first instant of the day the range ends at
        EventModel::factory()->published()->create([
            'title' => 'Partida a medianoche',
            'start_date' => new DateTimeImmutable('2026-10-31T23:00:00Z'),
            'end_date' => new DateTimeImmutable('2026-11-01T01:00:00Z'),
        ]);

        $this->getJson('/eventos/calendario?start=2026-10-30&end=2026-11-01')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Partida a medianoche']);

        $this->getJson('/eventos/calendario?start=2026-11-01&end=2026-11-02')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Partida a medianoche']);
    }

    public function test_the_sitemap_lastmod_is_written_in_the_display_zone(): void
    {
        EventModel::factory()->published()->create([
            'slug' => 'partida-lastmod',
            'updated_at' => new DateTimeImmutable('2026-07-01T10:00:00Z'),
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<lastmod>2026-07-01T12:00:00+02:00</lastmod>', false);
    }

    public function test_the_event_repository_date_range_takes_instants(): void
    {
        EventModel::factory()->published()->create([
            'start_date' => new DateTimeImmutable('2026-07-10T13:00:00Z'),
            'end_date' => new DateTimeImmutable('2026-07-10T13:30:00Z'),
        ]);
        $repository = new EloquentEventRepository;

        $this->assertCount(1, $repository->findByDateRange(
            new DateTimeImmutable('2026-07-10T12:00:00Z'),
            new DateTimeImmutable('2026-07-10T14:00:00Z'),
        ));
        $this->assertCount(0, $repository->findByDateRange(
            new DateTimeImmutable('2026-07-10T14:00:00Z'),
            new DateTimeImmutable('2026-07-10T15:00:00Z'),
        ));
    }

    public function test_the_event_repository_date_range_keeps_the_last_day_when_the_end_has_a_time(): void
    {
        EventModel::factory()->published()->create([
            'start_date' => new DateTimeImmutable('2026-07-10T09:00:00Z'),
            'end_date' => new DateTimeImmutable('2026-07-10T09:30:00Z'),
        ]);

        $this->assertCount(1, (new EloquentEventRepository)->findByDateRange(
            new DateTimeImmutable('2026-07-01T00:00:00Z'),
            new DateTimeImmutable('2026-07-10T14:00:00Z'),
        ));
    }

    public function test_the_mail_quota_day_starts_at_display_midnight(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24T22:30:00Z'));
        $this->setSetting('mail_quota_daily_limit', '10');

        EmailLogModel::factory()->create(['created_at' => new DateTimeImmutable('2026-10-24T22:10:00Z')]);
        EmailLogModel::factory()->create(['created_at' => new DateTimeImmutable('2026-10-24T21:50:00Z')]);

        $this->assertSame(1, app(EmailQuotaServiceInterface::class)->getQuotaStatus()->dailyUsed);
    }

    public function test_ses_usage_is_bucketed_by_display_day(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24T22:30:00Z'));

        (new SesUsageTracker)->incrementEmailsSent();

        $this->assertSame(
            '2026-10-25',
            SesUsageRecordModel::query()->firstOrFail()->date->format('Y-m-d'),
        );
        $this->assertSame(1, (new SesUsageTracker)->getTodayUsage()->emails_sent);
    }

    private function setSetting(string $key, string $value): void
    {
        app(SettingsServiceInterface::class)->set($key, $value);
    }
}
