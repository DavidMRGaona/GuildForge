<?php

declare(strict_types=1);

namespace App\Application\Search\Services;

use App\Application\Search\DTOs\SearchSyncResultDTO;

/**
 * Keeps the stored search documents and the search engine in line with the indexed sources.
 */
interface SearchIndexerInterface
{
    /**
     * Queues a refresh of one record once the current transaction commits; never throws.
     */
    public function refresh(string $type, string $sourceId): void;

    /**
     * Queues the removal of one record once the current transaction commits; never throws.
     */
    public function forget(string $type, string $sourceId): void;

    /**
     * Rebuilds one type, or every type, from its source right away: writes what the source offers,
     * removes what it no longer offers and, without a type, empties the types no source owns any more.
     */
    public function sync(?string $type = null): SearchSyncResultDTO;
}
