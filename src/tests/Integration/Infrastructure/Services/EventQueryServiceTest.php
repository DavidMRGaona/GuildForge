<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\Services\EventQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
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

    public function test_search_breaks_start_ties_by_creation_then_id(): void
    {
        $this->createTiedEvents('2020-05-01 18:00:00', otherStart: '2020-05-02 18:00:00', direction: 'desc');

        $this->assertSame(
            ['Campaign other', 'Campaign tie high id', 'Campaign tie low id', 'Campaign tie oldest'],
            $this->titles($this->service->searchPublished('campaign')),
        );
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
