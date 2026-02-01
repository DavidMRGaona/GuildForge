<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\ArticleQueryServiceInterface;
use App\Application\Services\TagQueryServiceInterface;
use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Requests\TagFilterRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\TagResource;
use Inertia\Inertia;
use Inertia\Response;

final class ArticleController extends Controller
{
    use BuildsPaginatedResponse;

    private const int PER_PAGE = 12;

    public function __construct(
        private readonly ArticleQueryServiceInterface $articleQuery,
        private readonly TagQueryServiceInterface $tagQuery,
    ) {
    }

    public function index(TagFilterRequest $request): Response
    {
        $page = $request->getPage();
        $tagSlugs = $request->getTagSlugs();

        $articles = $this->articleQuery->getPublishedPaginated($page, self::PER_PAGE, $tagSlugs);
        $total = $this->articleQuery->getPublishedTotal($tagSlugs);

        $availableTags = $this->tagQuery->getByType('articles');

        return Inertia::render('Articles/Index', [
            'articles' => $this->buildPaginatedResponse(
                items: $articles,
                total: $total,
                page: $page,
                perPage: self::PER_PAGE,
                resourceClass: ArticleResource::class,
            ),
            'tags' => TagResource::collection($availableTags)->resolve(),
            'currentTags' => $tagSlugs ?? [],
        ]);
    }

    public function show(string $slug): Response
    {
        $article = $this->articleQuery->findPublishedBySlug($slug);

        if ($article === null) {
            abort(404);
        }

        return Inertia::render('Articles/Show', [
            'article' => ArticleResource::make($article)->resolve(),
        ]);
    }
}
