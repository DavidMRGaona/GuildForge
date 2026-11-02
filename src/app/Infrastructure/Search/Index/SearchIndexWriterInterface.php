<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Index;

/**
 * Mirrors stored search documents in the search engine. Every method may throw when the engine is unreachable.
 */
interface SearchIndexWriterInterface
{
    public function syncSettings(): void;

    /**
     * @param  list<string>  $rowIds  uuids of stored rows to add or replace; ids without a row are skipped
     */
    public function put(array $rowIds): void;

    /**
     * @param  list<string>  $rowIds  uuids of rows already removed from the table
     */
    public function remove(array $rowIds): void;
}
