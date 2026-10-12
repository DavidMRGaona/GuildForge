<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Application\Services\ArticleQueryServiceInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\EventResource;
use Inertia\Inertia;
use Inertia\Response;

final class SearchController extends Controller
{
    private const MAX_RESULTS = 12;

    public function __construct(
        private readonly EventQueryServiceInterface $eventQuery,
        private readonly ArticleQueryServiceInterface $articleQuery,
        private readonly SeoMetadataFactoryInterface $seo,
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
            'error' => null,
            'seo' => $seo,
        ]);
    }
}
