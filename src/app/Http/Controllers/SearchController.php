<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Search\Contracts\SearchSourceInterface;
use App\Application\Search\DTOs\SearchResultDTO;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\ArticleQueryServiceInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\EventResource;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class SearchController extends Controller
{
    private const int MAX_RESULTS = 12;

    public function __construct(
        private readonly EventQueryServiceInterface $eventQuery,
        private readonly ArticleQueryServiceInterface $articleQuery,
        private readonly SeoMetadataFactoryInterface $seo,
        private readonly SearchSourceRegistryInterface $sources,
    ) {}

    public function __invoke(SearchRequest $request): Response
    {
        $query = $request->searchQuery();

        // Internal search results are not worth indexing
        $seo = $this->seo->forPage(new SeoPageDTO(
            title: (string) __('seo.titles.search'),
            noindex: true,
        ))->toArray();

        // Empty query returns empty results
        if ($query === '') {
            return Inertia::render('Search/Index', [
                'query' => '',
                'events' => [],
                'articles' => [],
                'sources' => [],
                'error' => null,
                'seo' => $seo,
            ]);
        }

        // Query too short
        if ($request->isQueryTooShort()) {
            return Inertia::render('Search/Index', [
                'query' => $query,
                'events' => [],
                'articles' => [],
                'sources' => [],
                'error' => 'minChars',
                'seo' => $seo,
            ]);
        }

        // Search using query services
        $events = $this->eventQuery->searchPublished($query, self::MAX_RESULTS);
        $articles = $this->articleQuery->searchPublished($query, self::MAX_RESULTS);

        return Inertia::render('Search/Index', [
            'query' => $query,
            'events' => EventResource::collection($events)->resolve(),
            'articles' => ArticleResource::collection($articles)->resolve(),
            'sources' => $this->searchSources($request, $query),
            'error' => null,
            'seo' => $seo,
        ]);
    }

    /**
     * Results of every registered source that has any; a source that fails is logged and skipped.
     *
     * @return list<array{key: string, label: string, results: list<array{title: string, url: string, context: ?string, snippet: list<array{text: string, match: bool}>}>}>
     */
    private function searchSources(SearchRequest $request, string $query): array
    {
        $blocks = [];

        foreach ($this->sources->sources() as $source) {
            try {
                $results = $source->search($query, $request->user(), self::MAX_RESULTS);
                $label = $source->label();
            } catch (Throwable $exception) {
                $this->logFailure($source, $exception);

                continue;
            }

            if ($results === []) {
                continue;
            }

            $blocks[] = [
                'key' => $source->key(),
                'label' => $label,
                'results' => array_map(static fn (SearchResultDTO $result): array => $result->toArray(), $results),
            ];
        }

        return $blocks;
    }

    private function logFailure(SearchSourceInterface $source, Throwable $exception): void
    {
        Log::warning('[SearchController] Search source failed, skipped', [
            'source' => $source::class,
            'error' => $exception->getMessage(),
        ]);
    }
}
