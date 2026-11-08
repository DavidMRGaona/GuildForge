<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\CalendarSourceRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Calendar\FakeCalendarSource;
use Tests\Support\Calendar\RangeRecordingCalendarSource;
use Tests\Support\Calendar\ThrowingCalendarSource;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

final class PublicCalendarFeedControllerTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    private const string URL = '/calendario.ics';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->travelTo(new DateTimeImmutable('2026-11-07 12:00:00 UTC'));
    }

    public function test_the_route_is_named_and_served_at_the_public_address(): void
    {
        $this->assertSame(url(self::URL), route('calendar.public.ics'));
    }

    public function test_the_feed_lists_the_public_entries_of_every_source(): void
    {
        $this->event('Torneo de otoño', '2026-11-20 17:00:00');
        $this->registerFakeSource([$this->moduleEntry('Partida de rol', '2026-11-21 18:00:00')]);

        $response = $this->get(self::URL);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString("\r\nSUMMARY:Torneo de otoño\r\n", $response->content());
        $this->assertStringContainsString("\r\nSUMMARY:Partida de rol\r\n", $response->content());
        $this->assertStringContainsString("\r\nX-WR-CALNAME:", $response->content());
        $this->assertSame(2, substr_count($response->content(), 'BEGIN:VEVENT'));
    }

    public function test_an_entry_the_public_calendar_hides_is_left_out(): void
    {
        EventModel::factory()->draft()->create([
            'title' => 'Borrador secreto',
            'start_date' => '2026-11-20 17:00:00',
            'end_date' => '2026-11-20 20:00:00',
        ]);

        $response = $this->get(self::URL);

        $response->assertOk();
        $this->assertStringNotContainsString('Borrador secreto', $response->content());
    }

    public function test_a_failing_source_does_not_break_the_feed(): void
    {
        $this->event('Torneo de otoño', '2026-11-20 17:00:00');
        app(CalendarSourceRegistryInterface::class)->register(ThrowingCalendarSource::class);

        $response = $this->get(self::URL);

        $response->assertOk();
        $this->assertStringContainsString('SUMMARY:Torneo de otoño', $response->content());
    }

    public function test_the_feed_covers_thirty_days_back_and_twelve_months_ahead(): void
    {
        $source = $this->registerRecordingSource();

        $this->get(self::URL)->assertOk();

        $this->assertNotNull($source->lastFrom);
        $this->assertNotNull($source->lastTo);
        $this->assertSame('2026-10-08T12:00:00+00:00', $source->lastFrom->format(DATE_ATOM));
        $this->assertSame('2027-11-07T12:00:00+00:00', $source->lastTo->format(DATE_ATOM));
    }

    public function test_the_feed_is_public_cacheable_and_not_indexed(): void
    {
        $response = $this->get(self::URL);

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
        $this->assertStringNotContainsString('private', $cacheControl);
        $this->assertNotNull($response->headers->get('ETag'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_feed_sets_no_cookies(): void
    {
        $response = $this->get(self::URL);

        $response->assertOk();
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_the_feed_is_the_same_for_a_signed_in_person(): void
    {
        $this->event('Torneo de otoño', '2026-11-20 17:00:00');
        $guest = $this->get(self::URL)->content();

        $signedIn = $this->actingAs(UserModel::factory()->create())->get(self::URL);

        $signedIn->assertOk();
        $this->assertSame($guest, $signedIn->content());
    }

    public function test_an_unchanged_feed_answers_not_modified(): void
    {
        $etag = (string) $this->get(self::URL)->headers->get('ETag');

        $response = $this->get(self::URL, ['If-None-Match' => $etag]);

        $response->assertStatus(304);
        $this->assertSame('', (string) $response->content());
    }

    public function test_the_feed_is_built_once_every_five_minutes(): void
    {
        $source = $this->registerRecordingSource();

        $this->get(self::URL)->assertOk();
        $this->travel(4)->minutes();
        $this->get(self::URL)->assertOk();

        $this->assertSame(1, $source->calls);

        $this->travel(2)->minutes();
        $this->get(self::URL)->assertOk();

        $this->assertSame(2, $source->calls);
    }

    public function test_the_feed_is_rate_limited_per_address(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->get(self::URL)->assertOk();
        }

        $this->get(self::URL)->assertTooManyRequests();
    }

    public function test_the_feed_does_not_share_the_limit_of_another_address(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->get(self::URL)->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get(self::URL)->assertOk();
    }

    public function test_the_feed_is_closed_while_the_site_is_in_maintenance(): void
    {
        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');

        $response = $this->get(self::URL);

        $response->assertStatus(503);
        $response->assertHeader('Retry-After');
        $this->assertSame([], $response->headers->getCookies());
    }

    /**
     * @param  array<CalendarEntryDTO>  $entries
     */
    private function registerFakeSource(array $entries): void
    {
        app()->instance(FakeCalendarSource::class, new FakeCalendarSource($entries));
        app(CalendarSourceRegistryInterface::class)->register(FakeCalendarSource::class);
    }

    private function registerRecordingSource(): RangeRecordingCalendarSource
    {
        $source = new RangeRecordingCalendarSource;
        app()->instance(RangeRecordingCalendarSource::class, $source);
        app(CalendarSourceRegistryInterface::class)->register(RangeRecordingCalendarSource::class);

        return $source;
    }

    private function moduleEntry(string $title, string $start): CalendarEntryDTO
    {
        $startsAt = new DateTimeImmutable($start.' UTC');

        return new CalendarEntryDTO(
            id: 'fake-1',
            sourceType: 'fake',
            sourceLabel: 'Fake source',
            color: CalendarSourceColor::Accent,
            title: $title,
            start: $startsAt,
            end: $startsAt->modify('+3 hours'),
            url: url('/partidas/fake-1'),
        );
    }

    private function event(string $title, string $start): EventModel
    {
        return EventModel::factory()->published()->create([
            'title' => $title,
            'start_date' => $start,
            'end_date' => (new DateTimeImmutable($start))->modify('+3 hours')->format('Y-m-d H:i:s'),
        ]);
    }
}
