<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Search;

use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;

/**
 * The rows of a page that are still visible and how many of each type the sources took away.
 *
 * Only what the page showed is checked, so the counts lose just those rows: the engine's offsets stay
 * what they were, a page may come back shorter, and "is there more" (offset + rows < total) is unchanged.
 *
 * @template TKey of array-key
 */
final readonly class CheckedSearchRows
{
    /**
     * @param  array<TKey, SearchDocumentModel>  $rows
     * @param  array<string, int>  $dropped  type => rows taken away
     */
    public function __construct(
        public array $rows,
        public array $dropped,
    ) {}

    public function droppedTotal(): int
    {
        return array_sum($this->dropped);
    }

    /**
     * The matches per type without the rows taken away; a type left with none is removed.
     *
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    public function adjust(array $counts): array
    {
        foreach ($this->dropped as $type => $dropped) {
            if (! isset($counts[$type])) {
                continue;
            }

            $left = $counts[$type] - $dropped;
            if ($left > 0) {
                $counts[$type] = $left;
            } else {
                unset($counts[$type]);
            }
        }

        return $counts;
    }
}
