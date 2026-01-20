<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\Response\ArticleResponseDTO;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\ArticleQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;

final readonly class ArticleQueryService implements ArticleQueryServiceInterface
{
    public function __construct(
        private ResponseDTOFactoryInterface $dtoFactory,
    ) {
    }

    public function getLatestPublished(int $limit = 10): array
    {
        $articles = ArticleModel::query()
            ->with('author')
            ->where('is_published', true)
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return $articles->map(fn (ArticleModel $article) => $this->dtoFactory->createArticleDTO($article))->all();
    }

    public function getPublishedPaginated(int $page = 1, int $perPage = 12): array
    {
        $offset = ($page - 1) * $perPage;

        $articles = ArticleModel::query()
            ->with('author')
            ->where('is_published', true)
            ->orderBy('published_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return $articles->map(fn (ArticleModel $article) => $this->dtoFactory->createArticleDTO($article))->all();
    }

    public function getPublishedTotal(): int
    {
        return ArticleModel::query()
            ->where('is_published', true)
            ->count();
    }

    public function findPublishedBySlug(string $slug): ?ArticleResponseDTO
    {
        $article = ArticleModel::query()
            ->with('author')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        return $article ? $this->dtoFactory->createArticleDTO($article) : null;
    }

    public function searchPublished(string $query, int $limit = 12): array
    {
        $articles = ArticleModel::query()
            ->with('author')
            ->where('is_published', true)
            ->where(function ($q) use ($query): void {
                $q->where('title', 'LIKE', "%{$query}%")
                    ->orWhere('content', 'LIKE', "%{$query}%")
                    ->orWhere('excerpt', 'LIKE', "%{$query}%");
            })
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return $articles->map(fn (ArticleModel $article) => $this->dtoFactory->createArticleDTO($article))->all();
    }
}
