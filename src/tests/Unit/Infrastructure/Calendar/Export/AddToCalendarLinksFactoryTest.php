<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Calendar\Export;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\AddToCalendarLinksFactoryInterface;
use App\Application\Calendar\Services\CalendarSourceRegistryInterface;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Calendar\FakeCalendarSource;
use Tests\Support\Calendar\FakeLookupCalendarSource;
use Tests\TestCase;

final class AddToCalendarLinksFactoryTest extends TestCase
{
    #[Test]
    public function the_google_link_prefills_the_event_in_utc(): void
    {
        $links = app(AddToCalendarLinksFactoryInterface::class)->for($this->entry());

        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?', $links->google);

        $query = $this->queryOf($links->google);
        $this->assertSame('TEMPLATE', $query['action']);
        $this->assertSame('Torneo de primavera', $query['text']);
        $this->assertSame('20260115T090000Z/20260115T190000Z', $query['dates']);
        $this->assertSame('Local de la asociación', $query['location']);
        $this->assertStringContainsString('Trae tu ejército.', $query['details']);
        $this->assertStringContainsString(url('/eventos/torneo-de-primavera'), $query['details']);
        $this->assertStringNotContainsString('<', $query['details']);
    }

    #[Test]
    public function the_outlook_link_prefills_the_event_in_utc(): void
    {
        $links = app(AddToCalendarLinksFactoryInterface::class)->for($this->entry());

        $this->assertStringStartsWith('https://outlook.live.com/calendar/0/deeplink/compose?', $links->outlook);

        $query = $this->queryOf($links->outlook);
        $this->assertSame('Torneo de primavera', $query['subject']);
        $this->assertSame('2026-01-15T09:00:00Z', $query['startdt']);
        $this->assertSame('2026-01-15T19:00:00Z', $query['enddt']);
        $this->assertSame('Local de la asociación', $query['location']);
        $this->assertStringContainsString(url('/eventos/torneo-de-primavera'), $query['body']);
    }

    #[Test]
    public function the_download_link_points_to_the_entry_when_its_source_can_find_it(): void
    {
        app()->instance(FakeLookupCalendarSource::class, new FakeLookupCalendarSource);
        app(CalendarSourceRegistryInterface::class)->register(FakeLookupCalendarSource::class);

        $links = app(AddToCalendarLinksFactoryInterface::class)->for(
            $this->entry(id: 'entry-7', sourceType: 'fake-lookup'),
        );

        $this->assertSame(url('/calendario/fake-lookup/entry-7.ics'), $links->ics);
    }

    #[Test]
    public function there_is_no_download_link_when_the_source_cannot_find_entries(): void
    {
        app()->instance(FakeCalendarSource::class, new FakeCalendarSource);
        app(CalendarSourceRegistryInterface::class)->register(FakeCalendarSource::class);

        $links = app(AddToCalendarLinksFactoryInterface::class)->for(
            $this->entry(id: 'entry-7', sourceType: 'fake'),
        );

        $this->assertNull($links->ics);
    }

    #[Test]
    public function links_serialize_for_the_frontend(): void
    {
        $links = app(AddToCalendarLinksFactoryInterface::class)->for($this->entry());

        $this->assertSame(
            ['google' => $links->google, 'outlook' => $links->outlook, 'ics' => $links->ics],
            $links->toArray(),
        );
    }

    private function entry(string $id = '0198a1b2-0000-7000-8000-000000000001', string $sourceType = 'event'): CalendarEntryDTO
    {
        return new CalendarEntryDTO(
            id: $id,
            sourceType: $sourceType,
            sourceLabel: 'Evento',
            color: CalendarSourceColor::Primary,
            title: 'Torneo de primavera',
            start: new DateTimeImmutable('2026-01-15 10:00:00', new DateTimeZone('Europe/Madrid')),
            end: new DateTimeImmutable('2026-01-15 20:00:00', new DateTimeZone('Europe/Madrid')),
            url: '/eventos/torneo-de-primavera',
            details: [
                'description' => '<p>Trae tu <em>ejército</em>.</p>',
                'location' => 'Local de la asociación',
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        /** @var array<string, string> $query */
        return $query;
    }
}
