<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\ArticleQueryServiceInterface;
use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\TagResource;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ArticleController extends Controller
{
    use BuildsPaginatedResponse;

    private const PER_PAGE = 12;

    public function __construct(
        private readonly ArticleQueryServiceInterface $articleQuery,
        private readonly ResponseDTOFactoryInterface $dtoFactory,
    ) {
    }

    public function index(Request $request): Response
    {
        $page = $this->getCurrentPage();
        $tagsParam = $request->query('tags');
        $tagSlugs = null;
        if (is_string($tagsParam) && $tagsParam !== '') {
            $tagSlugs = array_filter(explode(',', $tagsParam));
        }

        $articles = $this->articleQuery->getPublishedPaginated($page, self::PER_PAGE, $tagSlugs);
        $total = $this->articleQuery->getPublishedTotal($tagSlugs);

        $availableTags = TagModel::query()
            ->forType('articles')
            ->ordered()
            ->get()
            ->map(fn (TagModel $tag) => $this->dtoFactory->createTagDTO($tag))
            ->all();

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
