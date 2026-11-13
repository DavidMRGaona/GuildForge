<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Search;

use App\Application\Search\Contracts\ChecksSearchHits;
use App\Application\Search\Contracts\WithholdsSearchSnippets;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Closure;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Asks the sources that check their results which rows of a page are still visible, then the sources that
 * withhold snippets which of the rows left must be shown without their stored text: one call of each per type,
 * with every id of that type. Rows of a source without either check are kept as they are. Fails closed: a
 * visibility check that throws (a wrong return type included) drops every row of its type, and a snippet check
 * that throws shows every row of its type without a snippet.
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

        $visibilityCheckers = $this->sourcesImplementing(ChecksSearchHits::class);
        $snippetCheckers = $this->sourcesImplementing(WithholdsSearchSnippets::class);
        if ($visibilityCheckers === [] && $snippetCheckers === []) {
            return new CheckedSearchRows($rows, []);
        }

        $visible = [];
        foreach ($this->idsByType($rows, $visibilityCheckers) as $type => $ids) {
            $visible[$type] = $this->answeredIds(
                $type,
                $visibilityCheckers[$type],
                $ids,
                static fn (ChecksSearchHits $source): array => $source->visibleSearchHits($ids, $user),
                'A search source failed to check its results, they are left out: ',
            ) ?? [];
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

        $withheld = [];
        foreach ($this->idsByType($kept, $snippetCheckers) as $type => $ids) {
            // Fails closed: when the source cannot say, none of its stored snippets is shown
            $withheld[$type] = $this->answeredIds(
                $type,
                $snippetCheckers[$type],
                $ids,
                static fn (WithholdsSearchSnippets $source): array => $source->staleSearchSnippets($ids, $user),
                'A search source failed to check its snippets, its results are shown without them: ',
            ) ?? array_fill_keys($ids, true);
        }

        return new CheckedSearchRows($kept, $dropped, array_filter($withheld));
    }

    /**
     * @template TSource of object
     *
     * @param  class-string<TSource>  $contract
     * @return array<string, TSource> registered sources implementing the contract, by type
     */
    private function sourcesImplementing(string $contract): array
    {
        $found = [];
        foreach ($this->sources->indexedSources() as $source) {
            if (! $source instanceof $contract) {
                continue;
            }

            try {
                $found[$source->type()] = $source;
            } catch (Throwable) {
                // The registry already leaves out a source whose type cannot be read
                continue;
            }
        }

        return $found;
    }

    /**
     * @param  array<array-key, SearchDocumentModel>  $rows
     * @param  array<string, object>  $sources
     * @return array<string, non-empty-list<string>> distinct source ids, in page order, per type that has one of the sources
     */
    private function idsByType(array $rows, array $sources): array
    {
        $idsByType = [];
        foreach ($rows as $row) {
            if (isset($sources[$row->type])) {
                $idsByType[$row->type][$row->source_id] = true;
            }
        }

        return array_map(static fn (array $ids): array => array_map(strval(...), array_keys($ids)), $idsByType);
    }

    /**
     * The answer of one source, restricted to the ids asked for, or null when it failed (and was logged).
     *
     * @template TSource of object
     *
     * @param  TSource  $source
     * @param  non-empty-list<string>  $ids
     * @param  Closure(TSource): array<mixed>  $ask
     * @return array<string, true>|null
     */
    private function answeredIds(string $type, object $source, array $ids, Closure $ask, string $failure): ?array
    {
        try {
            // A wrong return type is a TypeError, failing closed like any other error
            $answer = $ask($source);
        } catch (Throwable $exception) {
            $this->logger->error('[SiteSearch] '.$failure.$exception->getMessage(), [
                'type' => $type,
                'source' => $source::class,
                'exception' => $exception,
            ]);

            return null;
        }

        return $this->askedFor($answer, $ids);
    }

    /**
     * The answered ids that were asked for: a source can only narrow what it was asked about.
     *
     * @param  array<mixed>  $answer  the elements are not checked by PHP, whatever the contract says
     * @param  non-empty-list<string>  $ids
     * @return array<string, true>
     */
    private function askedFor(array $answer, array $ids): array
    {
        $asked = array_fill_keys($ids, true);
        $answered = [];
        foreach ($answer as $id) {
            if (is_string($id) && isset($asked[$id])) {
                $answered[$id] = true;
            }
        }

        return $answered;
    }
}
