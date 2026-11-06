<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;

/**
 * Anonymous counters: nothing recorded here identifies a person.
 */
interface KnowledgeMetricsServiceInterface
{
    public function recordVote(GuideId $id, bool $helpful): void;

    /**
     * Counts a search that found nothing. Queries that look like personal data are dropped.
     */
    public function recordMiss(string $query, GuideAudience $audience): void;

    /**
     * Votes of the last days (today included, in the display calendar), per guide id.
     *
     * @return array<string, array{helpful: int, notHelpful: int}>
     */
    public function feedbackTotals(int $days): array;

    /**
     * Searches without results of the last days (today included), most frequent first.
     *
     * @return list<array{query: string, audience: string, count: int}>
     */
    public function topMisses(int $days, int $limit): array;

    /**
     * Deletes counters older than the given number of days and returns how many rows went.
     */
    public function prune(int $days): int;
}
