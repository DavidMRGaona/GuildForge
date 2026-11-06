<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Search;

use App\Application\Search\DTOs\SiteSearchHitDTO;
use App\Application\Search\DTOs\SiteSearchQueryDTO;
use App\Application\Search\DTOs\SiteSearchResultDTO;
use App\Application\Search\Services\SiteSearchInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Search\Enums\SearchAudience;
use App\Infrastructure\Knowledge\Search\SnippetBuilder;
use App\Infrastructure\Knowledge\Text\TextNormalizer;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use App\Infrastructure\Search\Support\QueryTerms;
use Illuminate\Database\Eloquent\Builder;

/**
 * Minimal search over the search_documents table, used when no search engine is configured or it fails.
 * Every term must appear, case-insensitively, in the title, keywords, meta or body; no typo tolerance,
 * accent folding or synonyms. Title matches go first, then what is not past, then the newest date.
 * The help guides (their own search) come after all the documents.
 */
final readonly class DatabaseSiteSearch implements SiteSearchInterface
{
    private const array FIELDS = ['title', 'keywords', 'meta', 'body'];

    /** Backslash escapes the LIKE wildcards; SQLite has no default escape character. */
    private const string LIKE = " LIKE ? ESCAPE '\\'";

    /** Sentence punctuation around a word; LIKE wildcards are kept and searched literally. */
    private const string EDGE_PUNCTUATION = '/^[¿?¡!.,;:"\'«»“”‘’()\[\]{}<>]+|[¿?¡!.,;:"\'«»“”‘’()\[\]{}<>]+$/u';

    /** "Past": finished, or its end (its start when it has no end) before now. */
    private const string PAST = 'CASE WHEN finished = ? OR COALESCE(ends_at, starts_at) < ? THEN 1 ELSE 0 END';

    public function __construct(
        private SnippetBuilder $snippets,
        private QueryTerms $queryTerms,
        private TimezoneServiceInterface $timezone,
        private HelpHitsProvider $help,
    ) {}

    public function search(SiteSearchQueryDTO $query): SiteSearchResultDTO
    {
        $terms = $this->terms($query->query);
        $audiences = array_values(array_unique(array_map(
            static fn (SearchAudience $audience): string => $audience->value,
            $query->audiences,
        )));
        // Without an audience no document is visible
        $types = $audiences === [] ? [] : array_values(array_unique($query->types));
        $offset = $query->offset;
        $limit = $query->limit;

        if ($terms === []) {
            return SiteSearchResultDTO::empty();
        }

        // The help centre's own search, every match so the help count is exact; the gate is checked again here.
        // Only the matches the page shows become hits below
        $user = $query->includeHelp && $query->user !== null && $this->help->availableFor($query->user) ? $query->user : null;
        $help = $user !== null ? $this->help->allMatches($user, $query->query) : [];

        if ($types === [] && $help === []) {
            return SiteSearchResultDTO::empty();
        }

        $counts = $types === [] ? [] : $this->counts($terms, $audiences, $types);
        if ($help !== []) {
            $counts[HelpHitsProvider::TYPE] = count($help);
        }

        $tabTypes = match ($query->type) {
            null => $types,
            HelpHitsProvider::TYPE => [],
            default => array_values(array_intersect($types, [$query->type])),
        };
        $tabHelp = $query->type === null || $query->type === HelpHitsProvider::TYPE ? $help : [];
        $documentTotal = array_sum(array_map(static fn (string $type): int => $counts[$type] ?? 0, $tabTypes));
        $total = $documentTotal + count($tabHelp);

        if ($total === 0 || $limit === 0) {
            return new SiteSearchResultDTO([], $counts, $total);
        }

        // Documents fill the first pages and the help rows the pages after them
        $hits = $offset < $documentTotal ? $this->documentHits($terms, $audiences, $tabTypes, $offset, $limit) : [];
        $remaining = $limit - count($hits);
        $shownHelp = $remaining > 0 ? array_slice($tabHelp, max(0, $offset - $documentTotal), $remaining) : [];
        if ($user !== null && $shownHelp !== []) {
            array_push($hits, ...array_values($this->help->fromKnowledgeHits($shownHelp, $user)));
        }

        return new SiteSearchResultDTO($hits, $counts, $total);
    }

    /**
     * @param  list<string>  $terms
     * @param  list<string>  $audiences
     * @param  list<string>  $types
     * @return list<SiteSearchHitDTO>
     */
    private function documentHits(array $terms, array $audiences, array $types, int $offset, int $limit): array
    {
        $now = $this->timezone->toStorage(now())->format('Y-m-d H:i:s');
        $titleMatches = implode(' + ', array_fill(0, count($terms), 'CASE WHEN LOWER(title)'.self::LIKE.' THEN 1 ELSE 0 END'));

        $rows = $this->matching($terms, $audiences, $types)
            ->select('*')
            ->selectRaw("({$titleMatches}) AS title_matches", array_map($this->pattern(...), $terms))
            ->selectRaw(self::PAST.' AS is_past', [true, $now])
            ->orderByDesc('title_matches')
            ->orderBy('is_past')
            // Undated rows after dated ones, the same on PostgreSQL and SQLite
            ->orderByRaw('CASE WHEN COALESCE(starts_at, published_at) IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(starts_at, published_at) DESC')
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $hits = [];
        foreach ($rows as $row) {
            $hits[] = new SiteSearchHitDTO(
                type: $row->type,
                title: $row->title,
                url: $row->url,
                meta: $row->meta,
                imagePublicId: $row->image_public_id,
                startsAt: $row->starts_at,
                endsAt: $row->ends_at,
                past: (int) $row->getAttribute('is_past') === 1,
                snippet: $this->snippet($row, $terms),
                publishedAt: $row->published_at,
            );
        }

        return $hits;
    }

    /**
     * Matches per type, in the order of the requested types; types without matches are left out.
     *
     * @param  list<string>  $terms
     * @param  list<string>  $audiences
     * @param  list<string>  $types
     * @return array<string, int>
     */
    private function counts(array $terms, array $audiences, array $types): array
    {
        $matches = $this->matching($terms, $audiences, $types)
            ->toBase()
            ->select('type')
            ->selectRaw('COUNT(*) AS matches')
            ->groupBy('type')
            ->pluck('matches', 'type');

        $counts = [];
        foreach ($types as $type) {
            $count = (int) ($matches[$type] ?? 0);
            if ($count > 0) {
                $counts[$type] = $count;
            }
        }

        return $counts;
    }

    /**
     * Rows of the given types and audiences where every term appears in some field.
     *
     * @param  list<string>  $terms
     * @param  list<string>  $audiences
     * @param  list<string>  $types
     * @return Builder<SearchDocumentModel>
     */
    private function matching(array $terms, array $audiences, array $types): Builder
    {
        $query = SearchDocumentModel::query()
            ->whereIn('type', $types)
            ->whereIn('audience', $audiences);

        foreach ($terms as $term) {
            $pattern = $this->pattern($term);
            $query->where(static function (Builder $fields) use ($pattern): void {
                foreach (self::FIELDS as $field) {
                    $fields->orWhereRaw("LOWER({$field})".self::LIKE, [$pattern]);
                }
            });
        }

        return $query;
    }

    private function pattern(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }

    /**
     * Lower-cased query words, accents kept: they are compared with LIKE.
     *
     * @return list<string>
     */
    private function terms(string $query): array
    {
        return $this->queryTerms->split($query, mb_strtolower(...), self::EDGE_PUNCTUATION);
    }

    /**
     * From the body when it contains a term, otherwise from the meta or the keywords that do,
     * otherwise the start of the body (the match was in the title).
     *
     * @param  list<string>  $terms
     * @return list<array{text: string, match: bool}>
     */
    private function snippet(SearchDocumentModel $row, array $terms): array
    {
        $normalizedTerms = array_map(TextNormalizer::normalize(...), $terms);

        foreach ([$row->body, $row->meta, $row->keywords] as $text) {
            if ($text === null || $text === '') {
                continue;
            }

            $normalized = TextNormalizer::normalize($text);
            foreach ($normalizedTerms as $term) {
                if (str_contains($normalized, $term)) {
                    return $this->snippets->build($text, $normalizedTerms);
                }
            }
        }

        return $this->snippets->build($row->body, $normalizedTerms);
    }
}
