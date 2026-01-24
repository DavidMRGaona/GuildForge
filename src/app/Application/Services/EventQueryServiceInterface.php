<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\Response\EventResponseDTO;

interface EventQueryServiceInterface
{
    /**
     * @return array<int, EventResponseDTO>
     */
    public function getUpcomingEvents(int $limit = 10): array;

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

    /**
     * @return array<int, EventResponseDTO>
     */
    public function searchPublished(string $query, int $limit = 12): array;

    /**
     * @return array<int, EventResponseDTO>
     */
    public function findByDateRange(string $start, string $end): array;
}
