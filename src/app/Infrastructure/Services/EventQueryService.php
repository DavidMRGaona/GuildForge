<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Support\QueryHelpers;

final readonly class EventQueryService implements EventQueryServiceInterface
{
    public function __construct(
        private ResponseDTOFactoryInterface $dtoFactory,
        private TimezoneServiceInterface $timezone,
    ) {}

    public function getUpcomingEvents(int $limit = 10): array
    {
        $events = EventModel::query()
            ->with('tags')
            ->where('is_published', true)
            ->where('start_date', '>=', now())
            ->orderBy('start_date', 'asc')
            ->limit($limit)
            ->get();

        return $events->map(fn (EventModel $event) => $this->dtoFactory->createEventDTO($event))->all();
    }

    public function getRecentPastEvents(int $limit = 10): array
    {
        $events = EventModel::query()
            ->with('tags')
            ->where('is_published', true)
            ->where('start_date', '<', now())
            ->orderBy('start_date', 'desc')
            ->limit($limit)
            ->get();

        return $events->map(fn (EventModel $event) => $this->dtoFactory->createEventDTO($event))->all();
    }

    public function getPublishedEventsPaginated(int $page = 1, int $perPage = 12, ?array $tagSlugs = null): array
    {
        $query = EventModel::query()
            ->with('tags')
            ->where('is_published', true);

        QueryHelpers::applyTagFilter($query, $tagSlugs);

        $events = QueryHelpers::applyPagination($query, $page, $perPage)
            ->orderBy('start_date', 'desc')
            ->get();

        return $events->map(fn (EventModel $event) => $this->dtoFactory->createEventDTO($event))->all();
    }

    public function getPublishedEventsTotal(?array $tagSlugs = null): int
    {
        $query = EventModel::query()
            ->where('is_published', true);

        return QueryHelpers::applyTagFilter($query, $tagSlugs)->count();
    }

    public function findPublishedBySlug(string $slug): ?EventResponseDTO
    {
        $event = EventModel::query()
            ->with('tags')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        return $event ? $this->dtoFactory->createEventDTO($event) : null;
    }

    public function findById(string $id): ?EventResponseDTO
    {
        $event = EventModel::query()
            ->with('tags')
            ->find($id);

        return $event ? $this->dtoFactory->createEventDTO($event) : null;
    }

    public function searchPublished(string $query, int $limit = 12): array
    {
        $searchTerm = '%'.mb_strtolower($query).'%';

        $events = EventModel::query()
            ->with('tags')
            ->where('is_published', true)
            ->where(function ($q) use ($searchTerm): void {
                $q->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(location) LIKE ?', [$searchTerm]);
            })
            ->orderBy('start_date', 'desc')
            ->limit($limit)
            ->get();

        return $events->map(fn (EventModel $event) => $this->dtoFactory->createEventDTO($event))->all();
    }

    public function findByDateRange(string $start, string $end): array
    {
        // The requested days are display days: bind their midnights as storage-zone instants.
        $start = $this->timezone->displayDayRange($start)[0]->format('Y-m-d H:i:s');
        $end = $this->timezone->displayDayRange($end)[0]->format('Y-m-d H:i:s');

        $events = EventModel::query()
            ->with('tags')
            ->where('is_published', true)
            ->where(function ($query) use ($start, $end): void {
                // The range ends before its end day starts (like FullCalendar's), so SQLite and
                // PostgreSQL agree on an event starting exactly at that midnight.
                // Event starts within range
                $query->where(function ($q) use ($start, $end): void {
                    $q->where('start_date', '>=', $start)->where('start_date', '<', $end);
                })
                    // Event ends within range (for events starting before range)
                    ->orWhere(function ($q) use ($start, $end): void {
                        $q->where('end_date', '>=', $start)->where('end_date', '<', $end);
                    })
                    // Event spans the entire range
                    ->orWhere(function ($q) use ($start, $end): void {
                        $q->where('start_date', '<', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->orderBy('start_date')
            ->get();

        return $events->map(fn (EventModel $event) => $this->dtoFactory->createEventDTO($event))->all();
    }
}
