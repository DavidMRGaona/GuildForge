<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Sources\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The search hits of a core source that are still visible, by the same query its document() reads.
 */
trait ChecksSearchableHits
{
    /**
     * @param  non-empty-list<string>  $ids
     * @return list<string>
     */
    public function visibleSearchHits(array $ids, ?object $user): array
    {
        // Keys are uuids: anything else matches no row, and PostgreSQL rejects it in the query
        $uuids = array_values(array_filter($ids, Str::isUuid(...)));
        if ($uuids === []) {
            return [];
        }

        $query = $this->searchable();

        /** @var list<string> */
        return $query
            ->whereKey($uuids)
            ->pluck($query->getModel()->getKeyName())
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * @return Builder<covariant Model>
     */
    abstract private function searchable(): Builder;
}
