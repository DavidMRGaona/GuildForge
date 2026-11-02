<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\CalendarSourceRegistryInterface;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Support\Calendar\FakeCalendarSource;
use Tests\Support\Calendar\FakeLookupCalendarSource;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

final class CalendarEntryICalendarControllerTest extends TestCase
{
    use LazilyRefreshDatabase;
    use UsesUtcStorage;

    public function test_a_published_event_downloads_as_icalendar(): void
    {
        $this->useUtcStorage();
        $event = EventModel::factory()->published()->create([
            'title' => 'Torneo de primavera',
            'slug' => 'torneo-de-primavera',
            'start_date' => '2026-04-18 10:00:00',
            'end_date' => '2026-04-18 20:00:00',
        ]);

        $response = $this->get("/calendario/event/{$event->id}.ics");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename=torneo-de-primavera.ics');
        $this->assertStringContainsString("\r\nSUMMARY:Torneo de primavera\r\n", $response->content());
        $this->assertStringContainsString("\r\nDTSTART:20260418T100000Z\r\n", $response->content());
        $this->assertStringContainsString("\r\nUID:event-{$event->id}@", $response->content());
    }

    public function test_an_unpublished_event_is_not_found(): void
    {
        $event = EventModel::factory()->draft()->create();

        $this->get("/calendario/event/{$event->id}.ics")->assertNotFound();
    }

    public function test_an_unknown_event_is_not_found(): void
    {
        $this->get('/calendario/event/0198a1b2-0000-7000-8000-000000000099.ics')->assertNotFound();
    }

    public function test_an_event_id_that_is_not_a_uuid_is_not_found(): void
    {
        $this->get('/calendario/event/not-a-uuid.ics')->assertNotFound();
    }

    public function test_an_unknown_source_is_not_found(): void
    {
        $this->get('/calendario/unknown/entry-1.ics')->assertNotFound();
    }

    public function test_a_module_source_that_can_find_entries_downloads_them(): void
    {
        $this->registerSource(new FakeLookupCalendarSource([$this->entry('entry-1', 'Partida de rol')]));

        $response = $this->get('/calendario/fake-lookup/entry-1.ics');

        $response->assertOk();
        $this->assertStringContainsString("\r\nSUMMARY:Partida de rol\r\n", $response->content());
    }

    public function test_an_entry_the_source_does_not_find_is_not_found(): void
    {
        $this->registerSource(new FakeLookupCalendarSource([$this->entry('entry-1', 'Partida de rol')]));

        $this->get('/calendario/fake-lookup/entry-2.ics')->assertNotFound();
    }

    public function test_a_source_without_lookup_is_not_found(): void
    {
        app()->instance(FakeCalendarSource::class, new FakeCalendarSource([$this->entry('entry-1', 'Partida')]));
        app(CalendarSourceRegistryInterface::class)->register(FakeCalendarSource::class);

        $this->get('/calendario/fake/entry-1.ics')->assertNotFound();
    }

    public function test_a_failing_lookup_is_not_found_and_logged(): void
    {
        $log = Log::spy();
        $this->registerSource(new FakeLookupCalendarSource(failure: new RuntimeException('Broken source.')));

        $this->get('/calendario/fake-lookup/entry-1.ics')->assertNotFound();

        $log->shouldHaveReceived('warning')->withArgs(
            fn (string $message): bool => str_contains($message, 'Calendar source failed to find an entry'),
        )->once();
    }

    public function test_the_event_page_offers_add_to_calendar_links(): void
    {
        $event = EventModel::factory()->published()->create(['slug' => 'torneo-de-primavera']);

        $this->get('/eventos/torneo-de-primavera')->assertInertia(
            fn (Assert $page) => $page
                ->component('Events/Show')
                ->where('calendarLinks.ics', url("/calendario/event/{$event->id}.ics"))
                ->where('calendarLinks.google', fn (string $url): bool => str_starts_with($url, 'https://calendar.google.com/'))
                ->where('calendarLinks.outlook', fn (string $url): bool => str_starts_with($url, 'https://outlook.live.com/'))
        );
    }

    public function test_calendar_entries_carry_add_to_calendar_links(): void
    {
        $event = EventModel::factory()->published()->create([
            'start_date' => '2025-01-15 10:00:00',
            'end_date' => '2025-01-15 18:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertOk();
        $response->assertJsonPath('0.calendarLinks.ics', url("/calendario/event/{$event->id}.ics"));
        $response->assertJsonPath('0.calendarLinks.google', fn (string $url): bool => str_starts_with($url, 'https://calendar.google.com/'));
    }

    private function registerSource(FakeLookupCalendarSource $source): void
    {
        app()->instance(FakeLookupCalendarSource::class, $source);
        app(CalendarSourceRegistryInterface::class)->register(FakeLookupCalendarSource::class);
    }

    private function entry(string $id, string $title): CalendarEntryDTO
    {
        return new CalendarEntryDTO(
            id: $id,
            sourceType: 'fake-lookup',
            sourceLabel: 'Fake lookup source',
            color: CalendarSourceColor::Accent,
            title: $title,
            start: new DateTimeImmutable('2026-05-02 17:00:00', new DateTimeZone('UTC')),
            end: new DateTimeImmutable('2026-05-02 21:00:00', new DateTimeZone('UTC')),
            url: '/mesas/partida-de-rol',
        );
    }
}
