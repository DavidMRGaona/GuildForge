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
            ->with('tags')
            ->where('is_published', true)
            ->where('start_date', '>=', now())
            ->orderBy('start_date', 'asc')
            ->limit($limit)
            ->get();

        return $events->map(fn (EventModel $event) => $this->dtoFactory->createEventDTO($event))->all();
    }

    public function getPublishedEventsPaginated(int $page = 1, int $perPage = 12, ?array $tagSlugs = null): array
    {
        $offset = ($page - 1) * $perPage;

        $query = EventModel::query()
            ->with('tags')
            ->where('is_published', true);

        if ($tagSlugs !== null && count($tagSlugs) > 0) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('slug', $tagSlugs));
        }

        $events = $query
            ->orderBy('start_date', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return $events->map(fn (EventModel $event) => $this->dtoFactory->createEventDTO($event))->all();
    }

    public function getPublishedEventsTotal(?array $tagSlugs = null): int
    {
        $query = EventModel::query()
            ->where('is_published', true);

        if ($tagSlugs !== null && count($tagSlugs) > 0) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('slug', $tagSlugs));
        }

        return $query->count();
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

    public function searchPublished(string $query, int $limit = 12): array
    {
        $searchTerm = '%' . mb_strtolower($query) . '%';

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
        $events = EventModel::query()
            ->with('tags')
            ->where('is_published', true)
            ->where(function ($query) use ($start, $end): void {
                // Event starts within range
                $query->whereBetween('start_date', [$start, $end])
                    // Event ends within range (for events starting before range)
                    ->orWhere(function ($q) use ($start, $end): void {
                        $q->whereNotNull('end_date')
                            ->whereBetween('end_date', [$start, $end]);
                    })
                    // Event spans the entire range (must have end_date)
                    ->orWhere(function ($q) use ($start, $end): void {
                        $q->where('start_date', '<', $start)
                            ->whereNotNull('end_date')
                            ->where('end_date', '>', $end);
                    });
            })
            ->orderBy('start_date')
            ->get();

        return $events->map(fn (EventModel $event) => $this->dtoFactory->createEventDTO($event))->all();
    }
}
