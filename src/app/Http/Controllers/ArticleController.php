<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Inertia\Inertia;
use Inertia\Response;

final class ArticleController extends Controller
{
    public function index(): Response
    {
        $articles = ArticleModel::query()
            ->where('is_published', true)
            ->with('author')
            ->orderBy('published_at', 'desc')
            ->paginate(12);

        return Inertia::render('Articles/Index', [
            'articles' => [
                'data' => $articles->map(fn (ArticleModel $article): array => $this->mapArticleModel($article)),
                'meta' => [
                    'currentPage' => $articles->currentPage(),
                    'lastPage' => $articles->lastPage(),
                    'perPage' => $articles->perPage(),
                    'total' => $articles->total(),
                ],
                'links' => [
                    'first' => $articles->url(1),
                    'last' => $articles->url($articles->lastPage()),
                    'prev' => $articles->previousPageUrl(),
                    'next' => $articles->nextPageUrl(),
                ],
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $article = ArticleModel::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('author')
            ->first();

        if ($article === null) {
            abort(404);
        }

        return Inertia::render('Articles/Show', [
            'article' => $this->mapArticleModel($article),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapArticleModel(ArticleModel $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'content' => $article->content,
            'excerpt' => $article->excerpt,
            'featuredImagePublicId' => $article->featured_image_public_id,
            'isPublished' => $article->is_published,
            'publishedAt' => $article->published_at?->format('c'),
            'author' => $this->mapAuthor($article->author),
            'createdAt' => $article->created_at?->format('c'),
            'updatedAt' => $article->updated_at?->format('c'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapAuthor(?UserModel $author): ?array
    {
        if ($author === null) {
            return null;
        }

        return [
            'id' => $author->id,
            'name' => $author->name,
            'displayName' => $author->display_name,
            'avatarPublicId' => $author->avatar_public_id,
        ];
    }
}
