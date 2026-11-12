<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Sources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The search hits of a core source whose records are visible while they are published, as in its document().
 */
trait ChecksPublishedHits
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

        $model = $this->searchedModel();

        /** @var list<string> */
        return $model::query()
            ->whereKey($uuids)
            ->where('is_published', true)
            ->pluck((new $model)->getKeyName())
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * @return class-string<Model>
     */
    abstract private function searchedModel(): string;
}
