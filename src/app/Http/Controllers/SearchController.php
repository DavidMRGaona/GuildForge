<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\ArticleQueryServiceInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\EventResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SearchController extends Controller
{
    private const MIN_QUERY_LENGTH = 2;
    private const MAX_RESULTS = 12;

    public function __construct(
        private readonly EventQueryServiceInterface $eventQuery,
        private readonly ArticleQueryServiceInterface $articleQuery,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));

        // Empty query returns empty results
        if ($query === '') {
            return Inertia::render('Search/Index', [
                'query' => '',
                'events' => [],
                'articles' => [],
                'error' => null,
            ]);
        }

        // Query too short
        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return Inertia::render('Search/Index', [
                'query' => $query,
                'events' => [],
                'articles' => [],
                'error' => 'minChars',
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
        ]);
    }
}
