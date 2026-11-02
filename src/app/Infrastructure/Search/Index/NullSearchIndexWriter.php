<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Index;

/**
 * Used when no search engine is configured: the database search reads the table directly.
 */
final readonly class NullSearchIndexWriter implements SearchIndexWriterInterface
{
    public function syncSettings(): void {}

    public function put(array $rowIds): void {}

    public function remove(array $rowIds): void {}
}
