<?php

declare(strict_types=1);

namespace App\Application\Search\Services;

use App\Application\Search\DTOs\SearchDocumentDTO;

/**
 * Persists the documents of indexed search sources, keyed by (type, source id).
 * Every method returns the row uuids it touched so the indexer can mirror them in the search engine.
 */
interface SearchDocumentStoreInterface
{
    /**
     * Creates or updates the document and returns the uuid of its row (stable across updates).
     */
    public function upsert(string $type, ?string $module, SearchDocumentDTO $doc): string;

    /**
     * Returns the uuid of the removed row, or null when there was none.
     */
    public function delete(string $type, string $sourceId): ?string;

    /**
     * Removes the documents of the type whose source id is not listed.
     *
     * @param  list<string>  $keepSourceIds
     * @return list<string> uuids of the removed rows
     */
    public function deleteExcept(string $type, array $keepSourceIds): array;

    /**
     * @return list<string> uuids of the removed rows
     */
    public function deleteType(string $type): array;
}
