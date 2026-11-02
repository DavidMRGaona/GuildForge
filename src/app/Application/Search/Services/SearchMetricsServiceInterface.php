<?php

declare(strict_types=1);

namespace App\Application\Search\Services;

/**
 * Anonymous counters of site searches that found nothing: per query and day, never tied to a user.
 */
interface SearchMetricsServiceInterface
{
    /**
     * Counts a search without results. Queries that look like personal data or are blank are dropped.
     */
    public function recordMiss(string $query): void;

    /**
     * The most frequent misses of the last $days display days, most frequent first.
     *
     * @return list<array{query: string, count: int}>
     */
    public function topMisses(int $days, int $limit): array;

    /**
     * Deletes the days older than the retention; returns the number of rows removed.
     */
    public function prune(int $days): int;
}
