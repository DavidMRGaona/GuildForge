<?php

declare(strict_types=1);

namespace App\Application\Search\DTOs;

/**
 * A page of results, the matches per type (types without matches are left out) and the matches of the tab asked for.
 */
final readonly class SiteSearchResultDTO
{
    /**
     * @param  list<SiteSearchHitDTO>  $hits
     * @param  array<string, int>  $counts  type => matches, 'help' included when the help is searched
     */
    public function __construct(
        public array $hits,
        public array $counts,
        public int $total,
    ) {}

    public static function empty(): self
    {
        return new self([], [], 0);
    }
}
