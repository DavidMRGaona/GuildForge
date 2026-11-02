<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;

class FakeIndexedSearchSource implements IndexedSearchSourceInterface
{
    public function type(): string
    {
        return 'fake-indexed';
    }

    public function label(): string
    {
        return 'Fake indexed';
    }

    public function itemLabel(): string
    {
        return 'Fake item';
    }

    public function pastLabel(): ?string
    {
        return null;
    }

    public function document(string $id): ?SearchDocumentDTO
    {
        return new SearchDocumentDTO($id, 'Title', 'Body', '/fake/'.$id);
    }

    public function documents(): iterable
    {
        yield new SearchDocumentDTO('1', 'Title', 'Body', '/fake/1');
    }
}
