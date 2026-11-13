<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Search;

use App\Application\Knowledge\Services\GuideVisibilityServiceInterface;
use App\Application\Search\DTOs\SiteSearchHitDTO;
use App\Application\Search\DTOs\SiteSearchQueryDTO;
use App\Application\Search\DTOs\SiteSearchResultDTO;
use App\Application\Search\Services\SiteSearchInterface;
use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Search\Enums\SearchAudience;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Knowledge\Search\MeilisearchHitMapper;
use App\Infrastructure\Knowledge\Search\MeilisearchKnowledgeSearch;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Illuminate\Support\Str;
use Meilisearch\Client;
use Meilisearch\Contracts\FederationOptions;
use Meilisearch\Contracts\MultiSearchFederation;
use Meilisearch\Contracts\SearchQuery;
use UnexpectedValueException;

/**
 * Searches the documents and the visible help guides in one federated Meilisearch request: what is not past
 * weighs more than what is past, so a good past match can still overtake a weak upcoming one.
 * Meilisearch only ranks: documents are read back from search_documents with the same type and audience
 * filter and checked again with the sources that check their results (SearchHitVisibility), guides from
 * the visible ones, and any other hit is discarded.
 */
final readonly class MeilisearchSiteSearch implements SiteSearchInterface
{
    public const string HELP_TYPE = HelpHitsProvider::TYPE;

    private const int MAX_QUERY_LENGTH = 500;

    private const int CROP_LENGTH = 24;

    private const float UPCOMING_WEIGHT = 1.0;

    private const float PAST_WEIGHT = 0.5;

    private const float HELP_WEIGHT = 1.0;

    /** In order of preference for the snippet, as in the database search. */
    private const array DOCUMENT_SNIPPET_FIELDS = ['body', 'meta', 'keywords'];

    /** Every guide the help filter lets through has the same locale, so this facet counts the help matches. */
    private const string HELP_COUNT_FACET = 'locale';

    public function __construct(
        private Client $client,
        private MeilisearchHitMapper $mapper,
        private MeilisearchKnowledgeSearch $knowledgeSearch,
        private GuideVisibilityServiceInterface $visibility,
        private HelpHitsProvider $help,
        private SearchHitVisibility $hitVisibility,
    ) {}

    public function search(SiteSearchQueryDTO $query): SiteSearchResultDTO
    {
        $text = trim(mb_substr($query->query, 0, self::MAX_QUERY_LENGTH));
        if ($text === '') {
            return SiteSearchResultDTO::empty();
        }

        $audiences = array_values(array_unique(array_map(
            static fn (SearchAudience $audience): string => $audience->value,
            $query->audiences,
        )));
        // Without an audience no document is visible
        $types = $audiences === [] ? [] : array_values(array_unique($query->types));
        // The gate is checked again here, whatever the caller asked for
        $guides = $query->includeHelp && $query->user !== null && $this->help->availableFor($query->user)
            ? $this->visibility->visibleGuides($query->user, GuideAudience::Site)
            : [];

        if ($types === [] && $guides === []) {
            return SiteSearchResultDTO::empty();
        }

        $tabTypes = match ($query->type) {
            null => $types,
            self::HELP_TYPE => [],
            default => array_values(array_intersect($types, [$query->type])),
        };
        $tabGuides = $query->type === null || $query->type === self::HELP_TYPE ? $guides : [];
        $now = now()->getTimestamp();
        // The DTO already rejects negative values; the engine's client wants that in the type
        $offset = max(0, $query->offset);
        $limit = max(0, $query->limit);

        if ($query->type === null) {
            // The facets of the federated request give the matches of every tab
            $response = $this->federated($text, $tabTypes, $audiences, $tabGuides, $now, $offset, $limit, withFacets: true);
            $counts = $this->counts($types, $this->federatedDistributions($response));
            $total = array_sum($counts);
        } else {
            // Facets follow the filter, and the tab's filter hides the other tabs: their matches need their own request
            $counts = $this->countsOfEveryTab($text, $types, $audiences, $guides);
            $total = $counts[$query->type] ?? 0;
            if ($total === 0 || $limit === 0) {
                return new SiteSearchResultDTO([], $counts, $total);
            }

            $response = $this->federated($text, $tabTypes, $audiences, $tabGuides, $now, $offset, $limit, withFacets: false);
        }

        [$hits, $checked] = $this->hits($response, $tabTypes, $audiences, $tabGuides, $query->user, $now);

        // Rows a source takes away leave this page shorter and the counts lower; the engine's offsets stay as they are
        return new SiteSearchResultDTO($hits, $checked->adjust($counts), $total - $checked->droppedTotal());
    }

    /**
     * Not past and past documents (two queries over the same index) and the guides, merged and paged by the engine.
     *
     * @param  list<string>  $types
     * @param  list<string>  $audiences
     * @param  list<Guide>  $guides
     * @param  int<0, max>  $offset
     * @param  int<0, max>  $limit
     * @return array<array-key, mixed>
     */
    private function federated(string $text, array $types, array $audiences, array $guides, int $now, int $offset, int $limit, bool $withFacets): array
    {
        $queries = [];
        $facets = [];

        if ($types !== []) {
            $documents = $this->documentsFilter($types, $audiences);
            $queries[] = $this->documentsQuery($text, "{$documents} AND finished = false AND (ends_at_ts >= {$now} OR ends_at_ts IS NULL)")
                ->setFederationOptions((new FederationOptions)->setWeight(self::UPCOMING_WEIGHT));
            $queries[] = $this->documentsQuery($text, "{$documents} AND (finished = true OR ends_at_ts < {$now})")
                ->setFederationOptions((new FederationOptions)->setWeight(self::PAST_WEIGHT));
            $facets[$this->documentsIndex()] = ['type'];
        }

        if ($guides !== []) {
            $queries[] = $this->guidesQuery($text, $guides)
                ->setFederationOptions((new FederationOptions)->setWeight(self::HELP_WEIGHT));
            $facets[$this->guidesIndex()] = [self::HELP_COUNT_FACET];
        }

        if ($queries === []) {
            return [];
        }

        $federation = (new MultiSearchFederation)->setLimit($limit)->setOffset($offset);
        if ($withFacets) {
            $federation->setFacetsByIndex($facets);
        }

        $response = $this->client->multiSearch($queries, $federation);

        // An answer without its hits (or its facets, when asked for) is not an answer: the fallback must take over
        if (! is_array($response) || ! is_array($response['hits'] ?? null) || ($withFacets && ! is_array($response['facetsByIndex'] ?? null))) {
            throw new UnexpectedValueException('The search engine answered with an unexpected shape.');
        }

        return $response;
    }

    /**
     * Matches of every tab, from a request without federation whose queries return no hits, only facets.
     *
     * @param  list<string>  $types
     * @param  list<string>  $audiences
     * @param  list<Guide>  $guides
     * @return array<string, int>
     */
    private function countsOfEveryTab(string $text, array $types, array $audiences, array $guides): array
    {
        $queries = [];
        if ($types !== []) {
            $queries[] = (new SearchQuery)
                ->setIndexUid($this->documentsIndex())
                ->setQuery($text)
                ->setFilter([$this->documentsFilter($types, $audiences)])
                ->setLimit(0)
                ->setFacets(['type']);
        }

        if ($guides !== []) {
            $queries[] = (new SearchQuery)
                ->setIndexUid($this->guidesIndex())
                ->setQuery($text)
                ->setFilter([$this->knowledgeSearch->filterFor($guides)])
                ->setLimit(0)
                ->setFacets([self::HELP_COUNT_FACET]);
        }

        if ($queries === []) {
            return [];
        }

        $response = $this->client->multiSearch($queries);
        $results = is_array($response) && is_array($response['results'] ?? null) ? $response['results'] : [];

        $distributions = [];
        foreach ($results as $result) {
            if (is_array($result) && is_string($result['indexUid'] ?? null)) {
                $distributions[$result['indexUid']] = $result['facetDistribution'] ?? null;
            }
        }

        return $this->counts($types, $distributions);
    }

    /**
     * @param  array<array-key, mixed>  $response
     * @return array<string, mixed> facet distribution per index uid
     */
    private function federatedDistributions(array $response): array
    {
        $distributions = [];
        foreach (is_array($response['facetsByIndex'] ?? null) ? $response['facetsByIndex'] : [] as $index => $facets) {
            $distributions[(string) $index] = is_array($facets) ? ($facets['distribution'] ?? null) : null;
        }

        return $distributions;
    }

    /**
     * Matches per type, in the order of the requested types, then the help; without the ones with no matches.
     *
     * @param  list<string>  $types
     * @param  array<string, mixed>  $distributions  facet distribution per index uid
     * @return array<string, int>
     */
    private function counts(array $types, array $distributions): array
    {
        $byType = $this->facet($distributions[$this->documentsIndex()] ?? null, 'type');

        $counts = [];
        foreach ($types as $type) {
            $count = $byType[$type] ?? 0;
            if ($count > 0) {
                $counts[$type] = $count;
            }
        }

        $help = array_sum($this->facet($distributions[$this->guidesIndex()] ?? null, self::HELP_COUNT_FACET));
        if ($help > 0) {
            $counts[self::HELP_TYPE] = $help;
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function facet(mixed $distribution, string $facet): array
    {
        $values = is_array($distribution) ? ($distribution[$facet] ?? null) : null;

        $counts = [];
        foreach (is_array($values) ? $values : [] as $value => $count) {
            if (is_int($count)) {
                $counts[(string) $value] = $count;
            }
        }

        return $counts;
    }

    /**
     * The merged hits in engine order; documents without a visible row, rows their source no longer shows
     * and guides that are not visible are dropped.
     *
     * @param  array<array-key, mixed>  $response
     * @param  list<string>  $types
     * @param  list<string>  $audiences
     * @param  list<Guide>  $guides
     * @return array{list<SiteSearchHitDTO>, CheckedSearchRows<string>}
     */
    private function hits(array $response, array $types, array $audiences, array $guides, ?object $user, int $now): array
    {
        $documentsIndex = $this->documentsIndex();
        $guidesIndex = $this->guidesIndex();

        $order = [];
        $documentIds = [];
        $guideHits = [];
        foreach (is_array($response['hits'] ?? null) ? $response['hits'] : [] as $rawHit) {
            if (! is_array($rawHit)) {
                continue;
            }

            $federation = is_array($rawHit['_federation'] ?? null) ? $rawHit['_federation'] : [];
            $index = $federation['indexUid'] ?? null;
            $formatted = is_array($rawHit['_formatted'] ?? null) ? $rawHit['_formatted'] : [];

            if ($index === $documentsIndex && is_string($rawHit['id'] ?? null) && Str::isUuid($rawHit['id'])) {
                $documentIds[] = $rawHit['id'];
                $order[] = ['document', $rawHit['id'], $formatted];
            } elseif ($index === $guidesIndex && is_string($rawHit['guide_id'] ?? null)) {
                $guideHits[] = $rawHit;
                $order[] = ['guide', $rawHit['guide_id'], $formatted];
            }
        }

        $checked = $this->hitVisibility->check($this->documents($documentIds, $types, $audiences), $user);
        $documents = $checked->rows;
        $help = $user !== null && $guides !== [] ? $this->help->fromKnowledgeHits($this->mapper->map(['hits' => $guideHits], $guides, count($guideHits)), $user) : [];

        $hits = [];
        foreach ($order as [$source, $id, $formatted]) {
            $key = $source.':'.$id;
            if (isset($hits[$key])) {
                continue;
            }

            if ($source === 'document' && isset($documents[$id])) {
                $hits[$key] = $this->documentHit($documents[$id], $checked->withholdsSnippet($documents[$id]) ? null : $formatted, $now);
            } elseif ($source === 'guide' && isset($help[$id])) {
                $hits[$key] = $help[$id];
            }
        }

        return [array_values($hits), $checked];
    }

    /**
     * The rows of the hits in one query, still limited to the requested types and audiences: the index may lag behind.
     *
     * @param  list<string>  $ids
     * @param  list<string>  $types
     * @param  list<string>  $audiences
     * @return array<string, SearchDocumentModel>
     */
    private function documents(array $ids, array $types, array $audiences): array
    {
        if ($ids === [] || $types === []) {
            return [];
        }

        $rows = SearchDocumentModel::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->whereIn('type', $types)
            ->whereIn('audience', $audiences)
            ->get();

        $documents = [];
        foreach ($rows as $row) {
            $documents[$row->id] = $row;
        }

        return $documents;
    }

    /**
     * @param  array<array-key, mixed>|null  $formatted  null when the source says the stored text no longer holds: title only
     */
    private function documentHit(SearchDocumentModel $row, ?array $formatted, int $now): SiteSearchHitDTO
    {
        // Past as in the database search: finished, or its end (its start when it has no end) before now
        $end = $row->ends_at ?? $row->starts_at;

        return new SiteSearchHitDTO(
            type: $row->type,
            title: $row->title,
            url: $row->url,
            meta: $row->meta,
            imagePublicId: $row->image_public_id,
            startsAt: $row->starts_at,
            endsAt: $row->ends_at,
            past: $row->finished || ($end !== null && $end->getTimestamp() < $now),
            snippet: $formatted === null ? [] : $this->documentSnippet($formatted),
            publishedAt: $row->published_at,
        );
    }

    /**
     * The first highlighted field among body, meta and keywords, otherwise the cropped start of the body.
     *
     * @param  array<array-key, mixed>  $formatted
     * @return list<array{text: string, match: bool}>
     */
    private function documentSnippet(array $formatted): array
    {
        foreach (self::DOCUMENT_SNIPPET_FIELDS as $field) {
            $text = $formatted[$field] ?? null;
            if (is_string($text) && str_contains($text, MeilisearchHitMapper::HIGHLIGHT_PRE_TAG)) {
                return $this->mapper->segments($text);
            }
        }

        return $this->mapper->segments(is_string($formatted['body'] ?? null) ? $formatted['body'] : '');
    }

    /**
     * @param  non-empty-string  $filter
     */
    private function documentsQuery(string $text, string $filter): SearchQuery
    {
        return (new SearchQuery)
            ->setIndexUid($this->documentsIndex())
            ->setQuery($text)
            ->setFilter([$filter])
            ->setAttributesToRetrieve(['id'])
            ->setAttributesToHighlight(self::DOCUMENT_SNIPPET_FIELDS)
            ->setAttributesToCrop(self::DOCUMENT_SNIPPET_FIELDS)
            ->setCropLength(self::CROP_LENGTH)
            ->setHighlightPreTag(MeilisearchHitMapper::HIGHLIGHT_PRE_TAG)
            ->setHighlightPostTag(MeilisearchHitMapper::HIGHLIGHT_POST_TAG);
    }

    /**
     * Highlighting and cropping as in the help centre's own search.
     *
     * @param  non-empty-list<Guide>  $guides
     */
    private function guidesQuery(string $text, array $guides): SearchQuery
    {
        return (new SearchQuery)
            ->setIndexUid($this->guidesIndex())
            ->setQuery($text)
            ->setFilter([$this->knowledgeSearch->filterFor($guides)])
            ->setAttributesToRetrieve(MeilisearchKnowledgeSearch::RETRIEVE_ATTRIBUTES)
            ->setAttributesToHighlight(MeilisearchKnowledgeSearch::HIGHLIGHT_ATTRIBUTES)
            ->setAttributesToCrop(MeilisearchKnowledgeSearch::CROP_ATTRIBUTES)
            ->setCropLength(MeilisearchKnowledgeSearch::CROP_LENGTH)
            ->setHighlightPreTag(MeilisearchHitMapper::HIGHLIGHT_PRE_TAG)
            ->setHighlightPostTag(MeilisearchHitMapper::HIGHLIGHT_POST_TAG);
    }

    /**
     * @param  non-empty-list<string>  $types
     * @param  list<string>  $audiences
     * @return non-empty-string
     */
    private function documentsFilter(array $types, array $audiences): string
    {
        return MeilisearchFilter::in('type', $types).' AND '.MeilisearchFilter::in('audience', $audiences);
    }

    /**
     * @return non-empty-string
     */
    private function documentsIndex(): string
    {
        return $this->indexUid((new SearchDocumentModel)->searchableAs());
    }

    /**
     * @return non-empty-string
     */
    private function guidesIndex(): string
    {
        return $this->indexUid((new KnowledgeGuideModel)->searchableAs());
    }

    /**
     * @return non-empty-string
     */
    private function indexUid(string $uid): string
    {
        if ($uid === '') {
            throw new UnexpectedValueException('A search index needs a name.');
        }

        return $uid;
    }
}
