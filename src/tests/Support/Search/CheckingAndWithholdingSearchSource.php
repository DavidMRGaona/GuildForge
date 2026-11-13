<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use App\Application\Search\Contracts\ChecksSearchHits;
use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\Contracts\WithholdsSearchSnippets;
use App\Application\Search\DTOs\SearchDocumentDTO;

/**
 * Indexed source with both checks: lets through the given ids and withholds the snippet of others,
 * recording every call. Bind an instance in the container, then register this class.
 */
final class CheckingAndWithholdingSearchSource implements ChecksSearchHits, IndexedSearchSourceInterface, WithholdsSearchSnippets
{
    /** @var list<list<string>> */
    public array $visibilityCalls = [];

    /** @var list<list<string>> */
    public array $snippetCalls = [];

    /**
     * @param  list<string>  $visible
     * @param  list<string>  $stale
     */
    public function __construct(
        private readonly string $type,
        private readonly array $visible,
        private readonly array $stale = [],
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
        return null;
    }

    public function documents(): iterable
    {
        return [];
    }

    public function visibleSearchHits(array $ids, ?object $user): array
    {
        $this->visibilityCalls[] = $ids;

        return array_values(array_intersect($ids, $this->visible));
    }

    public function staleSearchSnippets(array $ids, ?object $user): array
    {
        $this->snippetCalls[] = $ids;

        return array_values(array_intersect($ids, $this->stale));
    }
}
