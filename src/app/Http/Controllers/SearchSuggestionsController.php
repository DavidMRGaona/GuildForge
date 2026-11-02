<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SearchSuggestionsRequest;
use App\Http\Resources\SiteSearchHitResource;
use App\Http\Search\SiteSearchRunner;
use Illuminate\Http\JsonResponse;

/**
 * The header panel's suggestions: the best few results of every type mixed, and how many there are in all.
 * Always JSON, and never counted as a search without results (it would count words half typed).
 */
final class SearchSuggestionsController extends Controller
{
    public const int LIMIT = 6;

    public function __construct(private readonly SiteSearchRunner $search) {}

    public function __invoke(SearchSuggestionsRequest $request): JsonResponse
    {
        if (! $request->isSearchable()) {
            return response()->json(['results' => [], 'total' => 0]);
        }

        $outcome = $this->search->run($request->searchQuery(), $request->user(), null, 0, self::LIMIT);

        return response()->json([
            'results' => SiteSearchHitResource::rows($outcome->result->hits, $outcome->labels),
            'total' => $outcome->result->total,
        ]);
    }
}
