<?php

declare(strict_types=1);

namespace App\Application\Search\DTOs;

use App\Domain\Search\Enums\SearchAudience;
use InvalidArgumentException;

/**
 * One search of the site: what was typed, who may see what, and which page of which tab.
 */
final readonly class SiteSearchQueryDTO
{
    /**
     * @param  list<SearchAudience>  $audiences  audiences the person belongs to
     * @param  list<string>  $types  registered types of enabled sources; rows of any other type are ignored
     * @param  string|null  $type  the tab; null searches every type in $types
     * @param  object|null  $user  the signed-in person, for the help guides and the sources that check their results
     */
    public function __construct(
        public string $query,
        public array $audiences,
        public array $types,
        public ?string $type,
        public int $offset,
        public int $limit,
        public ?object $user,
        public bool $includeHelp,
    ) {
        if ($offset < 0 || $limit < 0) {
            throw new InvalidArgumentException('A search page needs a non-negative offset and limit.');
        }
    }
}
