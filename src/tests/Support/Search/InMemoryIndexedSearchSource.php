<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;
use RuntimeException;

/**
 * Indexed source whose documents are set by the test; documents() can be made to fail halfway.
 */
class InMemoryIndexedSearchSource implements IndexedSearchSourceInterface
{
    public int $documentsCalls = 0;

    /**
     * @param  list<SearchDocumentDTO>  $documents
     */
    public function __construct(
        private readonly string $type,
        public array $documents = [],
        public ?string $failWith = null,
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function label(): string
    {
        return 'Label';
    }

    public function itemLabel(): string
    {
        return 'Item';
    }

    public function pastLabel(): ?string
    {
        return null;
    }

    public function document(string $id): ?SearchDocumentDTO
    {
        foreach ($this->documents as $document) {
            if ($document->id === $id) {
                return $document;
            }
        }

        return null;
    }

    public function documents(): iterable
    {
        $this->documentsCalls++;

        foreach ($this->documents as $document) {
            yield $document;
        }

        if ($this->failWith !== null) {
            throw new RuntimeException($this->failWith);
        }
    }
}
