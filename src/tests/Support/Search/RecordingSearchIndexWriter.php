<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use App\Infrastructure\Search\Index\SearchIndexWriterInterface;
use RuntimeException;

/**
 * Records what the indexer sends to the search engine; can be made to fail like an unreachable engine.
 */
final class RecordingSearchIndexWriter implements SearchIndexWriterInterface
{
    public int $settingsSynced = 0;

    /** @var list<string> */
    public array $put = [];

    /** @var list<string> */
    public array $removed = [];

    public function __construct(
        public ?string $failWith = null,
    ) {}

    public function syncSettings(): void
    {
        $this->fail();
        $this->settingsSynced++;
    }

    public function put(array $rowIds): void
    {
        $this->fail();
        $this->put = [...$this->put, ...$rowIds];
    }

    public function remove(array $rowIds): void
    {
        $this->fail();
        $this->removed = [...$this->removed, ...$rowIds];
    }

    private function fail(): void
    {
        if ($this->failWith !== null) {
            throw new RuntimeException($this->failWith);
        }
    }
}
