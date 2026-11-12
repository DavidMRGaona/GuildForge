<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Search;

use App\Application\Search\Contracts\ChecksSearchHits;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Asks the sources that check their results which rows of a page are still visible: one call per type,
 * with every id of that type. Rows of a source without the check are kept as they are. Fails closed:
 * a check that throws (a wrong return type included) drops every row of its type.
 */
final readonly class SearchHitVisibility
{
    public function __construct(
        private SearchSourceRegistryInterface $sources,
        private LoggerInterface $logger,
    ) {}

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, SearchDocumentModel>  $rows
     * @return CheckedSearchRows<TKey>
     */
    public function check(array $rows, ?object $user): CheckedSearchRows
    {
        if ($rows === []) {
            return new CheckedSearchRows($rows, []);
        }

        $checkers = $this->checkers();
        $idsByType = [];
        foreach ($rows as $row) {
            if (isset($checkers[$row->type])) {
                $idsByType[$row->type][] = $row->source_id;
            }
        }

        if ($idsByType === []) {
            return new CheckedSearchRows($rows, []);
        }

        $visible = [];
        foreach ($idsByType as $type => $ids) {
            $visible[$type] = $this->visibleIds($checkers[$type], $type, array_values(array_unique($ids)), $user);
        }

        $kept = [];
        $dropped = [];
        foreach ($rows as $key => $row) {
            if (! isset($visible[$row->type]) || isset($visible[$row->type][$row->source_id])) {
                $kept[$key] = $row;

                continue;
            }

            $dropped[$row->type] = ($dropped[$row->type] ?? 0) + 1;
        }

        return new CheckedSearchRows($kept, $dropped);
    }

    /**
     * @return array<string, ChecksSearchHits> sources that check their results, by type
     */
    private function checkers(): array
    {
        $checkers = [];
        foreach ($this->sources->indexedSources() as $source) {
            if (! $source instanceof ChecksSearchHits) {
                continue;
            }

            try {
                $checkers[$source->type()] = $source;
            } catch (Throwable) {
                // The registry already leaves out a source whose type cannot be read
                continue;
            }
        }

        return $checkers;
    }

    /**
     * @param  non-empty-list<string>  $ids
     * @return array<string, true>
     */
    private function visibleIds(ChecksSearchHits $source, string $type, array $ids, ?object $user): array
    {
        try {
            // A wrong return type is a TypeError, failing closed like any other error
            $answer = $source->visibleSearchHits($ids, $user);
        } catch (Throwable $exception) {
            $this->logger->error('[SiteSearch] A search source failed to check its results, they are left out: '.$exception->getMessage(), [
                'type' => $type,
                'source' => $source::class,
                'exception' => $exception,
            ]);

            return [];
        }

        return $this->askedFor($answer, $ids);
    }

    /**
     * The answered ids that were asked for: a source can take results away, never add them.
     *
     * @param  array<mixed>  $answer  the elements are not checked by PHP, whatever the contract says
     * @param  non-empty-list<string>  $ids
     * @return array<string, true>
     */
    private function askedFor(array $answer, array $ids): array
    {
        $asked = array_fill_keys($ids, true);
        $visible = [];
        foreach ($answer as $id) {
            if (is_string($id) && isset($asked[$id])) {
                $visible[$id] = true;
            }
        }

        return $visible;
    }
}
