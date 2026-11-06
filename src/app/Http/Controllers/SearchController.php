<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Search\Services\SearchMetricsServiceInterface;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\SiteSearchHitResource;
use App\Http\Search\SiteSearchRunner;
use Inertia\Inertia;
use Inertia\Response;

final class SearchController extends Controller
{
    public const int PER_PAGE = 20;

    public function __construct(
        private readonly SiteSearchRunner $search,
        private readonly SearchMetricsServiceInterface $metrics,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function __invoke(SearchRequest $request): Response
    {
        $query = $request->searchQuery();
        $page = $request->page();

        if ($query === '' || $request->isQueryTooShort()) {
            return $this->render($query, $page, error: $request->isQueryTooShort() ? 'minChars' : null);
        }

        $outcome = $this->search->run($query, $request->user(), $request->tab(), ($page - 1) * self::PER_PAGE, self::PER_PAGE);
        $result = $outcome->result;

        // Only a real search of every tab counts: not a later page, not a tab, not a failure
        if ($result->total === 0 && $page === 1 && $outcome->type === null && ! $outcome->failed) {
            $this->metrics->recordMiss($query);
        }

        return $this->render(
            $query,
            $page,
            type: $outcome->type,
            tabs: $outcome->tabs((string) __('search.tabs.all')),
            results: SiteSearchHitResource::rows($result->hits, $outcome->labels),
            total: $result->total,
            // The engine pages no further than the last page the request accepts
            hasMore: $page < SearchRequest::MAX_PAGE && ($page - 1) * self::PER_PAGE + count($result->hits) < $result->total,
        );
    }

    /**
     * @param  list<array{key: string|null, label: string, count: int}>  $tabs
     * @param  list<array<string, mixed>>  $results
     */
    private function render(
        string $query,
        int $page,
        ?string $type = null,
        array $tabs = [],
        array $results = [],
        int $total = 0,
        bool $hasMore = false,
        ?string $error = null,
    ): Response {
        return Inertia::render('Search/Index', [
            'query' => $query,
            'type' => $type,
            'tabs' => $tabs,
            // The next page is appended to what the page already shows; a new query or tab resets it
            // Matched on the URL, so a row the next page repeats (the index changed in between) replaces
            // the one already shown instead of appearing twice
            'results' => Inertia::merge($results)->matchOn('url'),
            'total' => $total,
            'page' => $page,
            'hasMore' => $hasMore,
            'error' => $error,
            // Internal search results are not worth indexing
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('seo.titles.search'),
                noindex: true,
            ))->toArray(),
        ]);
    }
}
