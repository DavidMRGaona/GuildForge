<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use InvalidArgumentException;

/**
 * Which upcoming published events to list: at most $limit events starting from now until the
 * end of the $withinDays-th display day, counting today as the first (1 = the rest of today,
 * 7 = today and the six days after it). With tag slugs, only events with any of them.
 */
final readonly class UpcomingEventsCriteriaDTO
{
    /** @var list<string> */
    public array $tagSlugs;

    /**
     * @param  array<string>  $tagSlugs  blank and repeated slugs are dropped; none means no tag filter
     *
     * @throws InvalidArgumentException when the limit or the number of days is below 1
     */
    public function __construct(
        public int $limit,
        public int $withinDays,
        array $tagSlugs = [],
    ) {
        if ($limit < 1) {
            throw new InvalidArgumentException('The limit of upcoming events must be at least 1.');
        }

        if ($withinDays < 1) {
            throw new InvalidArgumentException('The horizon of upcoming events must be at least 1 day.');
        }

        $slugs = array_map(trim(...), $tagSlugs);

        $this->tagSlugs = array_values(array_unique(array_filter($slugs, fn (string $slug): bool => $slug !== '')));
    }
}
