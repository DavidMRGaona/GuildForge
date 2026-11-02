<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Index;

use Illuminate\Support\Facades\DB;

/**
 * Row ids already gone from search_documents that the search engine still has to forget.
 */
final readonly class PendingSearchRemovals
{
    private const string TABLE = 'search_pending_removals';

    private const int CHUNK = 500;

    /**
     * @param  list<string>  $rowIds
     */
    public function record(array $rowIds): void
    {
        $now = now();

        foreach (array_chunk(array_values(array_unique($rowIds)), self::CHUNK) as $chunk) {
            DB::table(self::TABLE)->insertOrIgnore(array_map(
                static fn (string $id): array => ['id' => $id, 'created_at' => $now],
                $chunk,
            ));
        }
    }

    /**
     * Pending ids in chunks; a chunk cleared while iterating is not read again.
     *
     * @return iterable<list<string>>
     */
    public function chunks(): iterable
    {
        $last = null;

        while (true) {
            $ids = array_values(DB::table(self::TABLE)
                ->when($last !== null, static fn ($query) => $query->where('id', '>', $last))
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->all());

            if ($ids === []) {
                return;
            }

            yield $ids;

            $last = $ids[count($ids) - 1];
        }
    }

    /**
     * @param  list<string>  $rowIds
     */
    public function clear(array $rowIds): void
    {
        foreach (array_chunk($rowIds, self::CHUNK) as $chunk) {
            DB::table(self::TABLE)->whereIn('id', $chunk)->delete();
        }
    }
}
