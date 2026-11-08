<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\DTOs\UpcomingEventsCriteriaDTO;
use App\Application\Services\EventQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class EventQueryServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private EventQueryServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(EventQueryServiceInterface::class);
    }

    public function test_upcoming_events_break_start_ties_by_creation_then_id(): void
    {
        $this->createTiedEvents('2099-05-01 18:00:00', otherStart: '2099-04-30 18:00:00', direction: 'asc');

        $this->assertSame(
            ['Campaign other', 'Campaign tie oldest', 'Campaign tie low id', 'Campaign tie high id'],
            $this->titles($this->service->getUpcomingEvents()),
        );
    }

    public function test_events_in_date_range_break_start_ties_by_creation_then_id(): void
    {
        $this->createTiedEvents('2099-05-01 18:00:00', otherStart: '2099-04-30 18:00:00', direction: 'asc');

        $this->assertSame(
            ['Campaign other', 'Campaign tie oldest', 'Campaign tie low id', 'Campaign tie high id'],
            $this->titles($this->service->findByDateRange('2099-04-30', '2099-05-02')),
        );
    }

    public function test_recent_past_events_break_start_ties_by_creation_then_id(): void
    {
        $this->createTiedEvents('2020-05-01 18:00:00', otherStart: '2020-05-02 18:00:00', direction: 'desc');

        $this->assertSame(
            ['Campaign other', 'Campaign tie high id', 'Campaign tie low id', 'Campaign tie oldest'],
            $this->titles($this->service->getRecentPastEvents()),
        );
    }

    public function test_published_paginated_events_break_start_ties_by_creation_then_id(): void
    {
        $this->createTiedEvents('2020-05-01 18:00:00', otherStart: '2020-05-02 18:00:00', direction: 'desc');

        $this->assertSame(
            ['Campaign other', 'Campaign tie high id', 'Campaign tie low id', 'Campaign tie oldest'],
            $this->titles($this->service->getPublishedEventsPaginated()),
        );
    }

    public function test_published_paginated_upcoming_events_break_start_ties_by_creation_then_id(): void
    {
        $this->createTiedEvents('2099-05-01 18:00:00', otherStart: '2099-04-30 18:00:00', direction: 'asc');

        $this->assertSame(
            ['Campaign other', 'Campaign tie oldest', 'Campaign tie low id', 'Campaign tie high id'],
            $this->titles($this->service->getPublishedEventsPaginated()),
        );
    }

    public function test_published_paginated_events_list_upcoming_soonest_first_then_past_latest_first(): void
    {
        $this->travelTo('2026-06-15 12:00:00');

        foreach ([
            'Past old' => '2026-01-10 18:00:00',
            'Upcoming far' => '2026-12-01 18:00:00',
            'Past recent' => '2026-06-14 18:00:00',
            'Upcoming tomorrow' => '2026-06-16 18:00:00',
            'Upcoming next month' => '2026-07-20 18:00:00',
            'Started an hour ago' => '2026-06-15 11:00:00',
        ] as $title => $start) {
            EventModel::factory()->published()->create(['title' => $title, 'start_date' => $start, 'end_date' => $start]);
        }

        $this->assertSame(
            ['Upcoming tomorrow', 'Upcoming next month', 'Upcoming far', 'Started an hour ago', 'Past recent', 'Past old'],
            $this->titles($this->service->getPublishedEventsPaginated()),
        );
        $this->assertSame(
            ['Upcoming far', 'Started an hour ago'],
            $this->titles($this->service->getPublishedEventsPaginated(page: 2, perPage: 2)),
        );
    }

    public function test_upcoming_events_within_days_end_at_the_display_midnight(): void
    {
        // 00:30 on 16 June in the display zone (Madrid), still 15 June in UTC
        $this->travelTo(CarbonImmutable::parse('2026-06-15 22:30:00', 'UTC'));

        foreach ([
            'Started half an hour ago' => '2026-06-15 22:00:00',
            'Later today' => '2026-06-16 12:00:00',
            'Last minute of today' => '2026-06-16 21:59:00',
            'Tomorrow at midnight' => '2026-06-16 22:00:00',
        ] as $title => $start) {
            $this->createEventStartingAt($title, $start);
        }

        $this->assertSame(
            ['Later today', 'Last minute of today'],
            $this->titles($this->service->getUpcomingEventsWithin(new UpcomingEventsCriteriaDTO(limit: 10, withinDays: 1))),
        );
        $this->assertSame(
            ['Later today', 'Last minute of today', 'Tomorrow at midnight'],
            $this->titles($this->service->getUpcomingEventsWithin(new UpcomingEventsCriteriaDTO(limit: 10, withinDays: 2))),
        );
    }

    public function test_upcoming_events_within_days_are_published_soonest_first_and_limited(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));

        $this->createEventStartingAt('Third', '2026-06-20 18:00:00');
        $this->createEventStartingAt('First', '2026-06-16 18:00:00');
        $this->createEventStartingAt('Second', '2026-06-18 18:00:00');
        $this->createEventStartingAt('Draft', '2026-06-17 18:00:00', published: false);

        $this->assertSame(
            ['First', 'Second'],
            $this->titles($this->service->getUpcomingEventsWithin(new UpcomingEventsCriteriaDTO(limit: 2, withinDays: 30))),
        );
    }

    public function test_upcoming_events_within_days_break_start_ties_by_creation_then_id(): void
    {
        $this->travelTo(CarbonImmutable::parse('2099-04-29 10:00:00', 'UTC'));
        $this->createTiedEvents('2099-05-01 18:00:00', otherStart: '2099-04-30 18:00:00', direction: 'asc');

        $this->assertSame(
            ['Campaign other', 'Campaign tie oldest', 'Campaign tie low id', 'Campaign tie high id'],
            $this->titles($this->service->getUpcomingEventsWithin(new UpcomingEventsCriteriaDTO(limit: 10, withinDays: 7))),
        );
    }

    public function test_upcoming_events_within_days_keep_those_with_any_of_the_tags(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00:00', 'UTC'));

        $roleplaying = TagModel::factory()->forEvents()->create(['slug' => 'roleplaying']);
        $wargames = TagModel::factory()->forEvents()->create(['slug' => 'wargames']);
        $boardGames = TagModel::factory()->forEvents()->create(['slug' => 'board-games']);

        $this->createEventStartingAt('Campaign night', '2026-06-16 18:00:00')->tags()->attach($roleplaying);
        $this->createEventStartingAt('Skirmish', '2026-06-17 18:00:00')->tags()->attach([$wargames->id, $roleplaying->id]);
        $this->createEventStartingAt('Board game day', '2026-06-18 18:00:00')->tags()->attach($boardGames);
        $this->createEventStartingAt('Untagged', '2026-06-19 18:00:00');

        $this->assertSame(
            ['Campaign night', 'Skirmish'],
            $this->titles($this->service->getUpcomingEventsWithin(
                new UpcomingEventsCriteriaDTO(limit: 10, withinDays: 30, tagSlugs: ['roleplaying', 'wargames']),
            )),
        );
        $this->assertSame(
            ['Campaign night', 'Skirmish', 'Board game day', 'Untagged'],
            $this->titles($this->service->getUpcomingEventsWithin(new UpcomingEventsCriteriaDTO(limit: 10, withinDays: 30))),
        );
    }

    /**
     * @param  string  $start  UTC wall time
     */
    private function createEventStartingAt(string $title, string $start, bool $published = true): EventModel
    {
        $instant = CarbonImmutable::parse($start, 'UTC');

        return EventModel::factory()->create([
            'title' => $title,
            'is_published' => $published,
            'start_date' => $instant,
            'end_date' => $instant->addHours(3),
        ]);
    }

    /**
     * Three events starting at the same time plus one that sorts before them. The tied events
     * are inserted in the reverse of the order expected for $direction, so they only come out
     * right when the query breaks the ties.
     *
     * @param  'asc'|'desc'  $direction
     */
    private function createTiedEvents(string $tieStart, string $otherStart, string $direction): void
    {
        $tied = [
            ['title' => 'Campaign tie oldest', 'created_at' => '2026-02-01 09:00:00'],
            ['id' => '11111111-1111-4111-8111-111111111111', 'title' => 'Campaign tie low id', 'created_at' => '2026-02-02 09:00:00'],
            ['id' => 'ffffffff-ffff-4fff-bfff-ffffffffffff', 'title' => 'Campaign tie high id', 'created_at' => '2026-02-02 09:00:00'],
        ];

        if ($direction === 'asc') {
            $tied = array_reverse($tied);
        }

        foreach ($tied as $attributes) {
            EventModel::factory()->published()->create([
                ...$attributes,
                'start_date' => $tieStart,
                'end_date' => $tieStart,
            ]);
        }

        EventModel::factory()->published()->create([
            'title' => 'Campaign other',
            'start_date' => $otherStart,
            'end_date' => $otherStart,
        ]);
    }

    /**
     * @param  array<EventResponseDTO>  $events
     * @return list<string>
     */
    private function titles(array $events): array
    {
        return array_values(array_map(fn (EventResponseDTO $event): string => $event->title, $events));
    }
}
