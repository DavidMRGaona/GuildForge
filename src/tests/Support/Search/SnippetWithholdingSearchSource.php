<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\Contracts\WithholdsSearchSnippets;
use App\Application\Search\DTOs\SearchDocumentDTO;
use Closure;

/**
 * Indexed source that withholds stored snippets with a closure set by the test, and records every call.
 * Bind an instance in the container, then register this class.
 */
final class SnippetWithholdingSearchSource implements IndexedSearchSourceInterface, WithholdsSearchSnippets
{
    /** @var list<array{ids: list<string>, user: object|null}> */
    public array $calls = [];

    /**
     * @param  Closure(list<string>, object|null): mixed  $stale
     */
    public function __construct(
        private readonly string $type,
        private readonly Closure $stale,
    ) {}

    /**
     * A source whose stored text is out of date for the given ids.
     *
     * @param  list<string>  $stale
     */
    public static function withholding(string $type, array $stale): self
    {
        return new self($type, static fn (array $ids): array => array_values(array_intersect($ids, $stale)));
    }

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

    public function staleSearchSnippets(array $ids, ?object $user): array
    {
        $this->calls[] = ['ids' => $ids, 'user' => $user];

        /** @var list<string> */
        return ($this->stale)($ids, $user);
    }
}
