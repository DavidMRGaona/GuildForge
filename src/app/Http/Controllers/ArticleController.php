<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\ArticleQueryServiceInterface;
use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Resources\ArticleResource;
use Inertia\Inertia;
use Inertia\Response;

final class ArticleController extends Controller
{
    use BuildsPaginatedResponse;

    private const PER_PAGE = 12;

    public function __construct(
        private readonly ArticleQueryServiceInterface $articleQuery,
    ) {
    }

    public function index(): Response
    {
        $page = $this->getCurrentPage();

        $articles = $this->articleQuery->getPublishedPaginated($page, self::PER_PAGE);
        $total = $this->articleQuery->getPublishedTotal();

        return Inertia::render('Articles/Index', [
            'articles' => $this->buildPaginatedResponse(
                items: $articles,
                total: $total,
                page: $page,
                perPage: self::PER_PAGE,
                resourceClass: ArticleResource::class,
            ),
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
