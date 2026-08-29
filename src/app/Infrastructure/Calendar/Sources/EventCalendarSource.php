<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar\Sources;

use App\Application\Calendar\Contracts\CalendarEntrySourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\Services\EventQueryServiceInterface;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateTimeImmutable;

final class EventCalendarSource implements CalendarEntrySourceInterface
{
    public function __construct(
        private readonly EventQueryServiceInterface $eventQueryService,
    ) {}

    public function sourceType(): string
    {
        return 'event';
    }

    public function sourceLabel(): string
    {
        return __('calendar.sources.event');
    }

    public function color(): CalendarSourceColor
    {
        return CalendarSourceColor::Primary;
    }

    /**
     * @return array<CalendarEntryDTO>
     */
    public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $events = $this->eventQueryService->findByDateRange(
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        );

        return array_map(
            fn (EventResponseDTO $event): CalendarEntryDTO => $this->toEntry($event),
            $events,
        );
    }

    private function toEntry(EventResponseDTO $event): CalendarEntryDTO
    {
        $tags = array_map(fn ($tag): array => [
            'id' => $tag->id,
            'name' => $tag->name,
            'slug' => $tag->slug,
            'color' => $tag->color,
            'parentId' => $tag->parentId,
        ], $event->tags);

        return new CalendarEntryDTO(
            id: $event->id,
            sourceType: $this->sourceType(),
            sourceLabel: $this->sourceLabel(),
            color: $this->color(),
            title: $event->title,
            start: $event->startDate,
            end: $event->endDate,
            url: '/eventos/'.$event->slug,
            details: [
                'slug' => $event->slug,
                'description' => $event->description,
                'location' => $event->location,
                'imagePublicId' => $event->imagePublicId,
                'memberPrice' => $event->memberPrice,
                'nonMemberPrice' => $event->nonMemberPrice,
                'tags' => $tags,
            ],
        );
    }
}
