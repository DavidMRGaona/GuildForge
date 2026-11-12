<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use App\Application\Search\Contracts\ChecksSearchHits;
use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;
use Closure;

/**
 * Indexed source that checks its results with a closure set by the test, and records every check.
 * Bind an instance in the container, then register this class.
 */
final class CheckingSearchSource implements ChecksSearchHits, IndexedSearchSourceInterface
{
    /** @var list<array{ids: list<string>, user: object|null}> */
    public array $checks = [];

    /**
     * @param  Closure(list<string>, object|null): mixed  $check
     */
    public function __construct(
        private readonly string $type,
        private readonly Closure $check,
    ) {}

    /**
     * A source that only lets through the given ids.
     *
     * @param  list<string>  $visible
     */
    public static function allowing(string $type, array $visible): self
    {
        return new self($type, static fn (array $ids): array => array_values(array_intersect($ids, $visible)));
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

    public function visibleSearchHits(array $ids, ?object $user): array
    {
        $this->checks[] = ['ids' => $ids, 'user' => $user];

        /** @var list<string> */
        return ($this->check)($ids, $user);
    }
}
