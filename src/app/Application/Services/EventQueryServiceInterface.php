<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\DTOs\UpcomingEventsCriteriaDTO;

interface EventQueryServiceInterface
{
    /**
     * @return array<int, EventResponseDTO>
     */
    public function getUpcomingEvents(int $limit = 10): array;

    /**
     * Published events starting from now until the end of the criteria's last display day,
     * soonest first (ties by creation, then id), with any of its tags when it has some.
     *
     * @return array<int, EventResponseDTO>
     */
    public function getUpcomingEventsWithin(UpcomingEventsCriteriaDTO $criteria): array;

    /**
     * @return array<int, EventResponseDTO>
     */
    public function getRecentPastEvents(int $limit = 10): array;

    /**
     * @param  array<string>|null  $tagSlugs
     * @return array<int, EventResponseDTO>
     */
    public function getPublishedEventsPaginated(int $page = 1, int $perPage = 12, ?array $tagSlugs = null): array;

    /**
     * @param  array<string>|null  $tagSlugs
     */
    public function getPublishedEventsTotal(?array $tagSlugs = null): int;

    public function findPublishedBySlug(string $slug): ?EventResponseDTO;

    public function findById(string $id): ?EventResponseDTO;

    /**
     * The range is [start, end): the end day is exclusive. Both arguments are display days;
     * the implementation turns their midnights into storage-zone instants before comparing.
     *
     * @return array<int, EventResponseDTO>
     */
    public function findByDateRange(string $start, string $end): array;
}
