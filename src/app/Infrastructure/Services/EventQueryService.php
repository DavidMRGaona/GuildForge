<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;

final readonly class EventQueryService implements EventQueryServiceInterface
{
    public function __construct(
        private ResponseDTOFactoryInterface $dtoFactory,
    ) {
    }

    public function getUpcomingEvents(int $limit = 10): array
    {
        $events = EventModel::query()
            ->where('is_published', true)
            ->where('start_date', '>=', now())
            ->orderBy('start_date', 'asc')
            ->limit($limit)
            ->get();

        return $events->map(fn (EventModel $event) => $this->dtoFactory->createEventDTO($event))->all();
    }

    public function getPublishedEventsPaginated(int $page = 1, int $perPage = 12): array
    {
        $offset = ($page - 1) * $perPage;

        $events = EventModel::query()
            ->where('is_published', true)
            ->orderBy('start_date', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return $events->map(fn (EventModel $event) => $this->dtoFactory->createEventDTO($event))->all();
    }

    public function getPublishedEventsTotal(): int
    {
        return EventModel::query()
            ->where('is_published', true)
            ->count();
    }

    public function findPublishedBySlug(string $slug): ?EventResponseDTO
    {
        $event = EventModel::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        return $event ? $this->dtoFactory->createEventDTO($event) : null;
    }

    public function searchPublished(string $query, int $limit = 12): array
    {
        $events = EventModel::query()
            ->where('is_published', true)
            ->where(function ($q) use ($query): void {
                $q->where('title', 'LIKE', "%{$query}%")
                    ->orWhere('description', 'LIKE', "%{$query}%")
                    ->orWhere('location', 'LIKE', "%{$query}%");
            })
            ->orderBy('start_date', 'desc')
            ->limit($limit)
            ->get();

        return $events->map(fn (EventModel $event) => $this->dtoFactory->createEventDTO($event))->all();
    }
}
