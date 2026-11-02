<?php

declare(strict_types=1);

namespace App\Application\Search\Contracts;

use App\Application\Search\DTOs\SearchResultDTO;

/**
 * A place the site search can look in besides events and articles (for instance the help centre).
 */
interface SearchSourceInterface
{
    /**
     * Stable identifier of the source, unique among registered sources.
     */
    public function key(): string;

    /**
     * Heading shown above the results, already translated.
     */
    public function label(): string;

    /**
     * Results the given user may see; an empty list when there is nothing to show.
     *
     * @return list<SearchResultDTO>
     */
    public function search(string $query, ?object $user, int $limit): array;
}
