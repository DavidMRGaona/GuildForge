<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\Response\ArticleResponseDTO;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\ArticleQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Support\QueryHelpers;

final readonly class ArticleQueryService implements ArticleQueryServiceInterface
{
    public function __construct(
        private ResponseDTOFactoryInterface $dtoFactory,
    ) {}

    public function getLatestPublished(int $limit = 10): array
    {
        $articles = ArticleModel::query()
            ->with(['author', 'tags'])
            ->where('is_published', true)
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return $articles->map(fn (ArticleModel $article) => $this->dtoFactory->createArticleDTO($article))->all();
    }

    public function getPublishedPaginated(int $page = 1, int $perPage = 12, ?array $tagSlugs = null): array
    {
        $query = ArticleModel::query()
            ->with(['author', 'tags'])
            ->where('is_published', true);

        QueryHelpers::applyTagFilter($query, $tagSlugs);

        $articles = QueryHelpers::applyPagination($query, $page, $perPage)
            ->orderBy('published_at', 'desc')
            ->get();

        return $articles->map(fn (ArticleModel $article) => $this->dtoFactory->createArticleDTO($article))->all();
    }

    public function getPublishedTotal(?array $tagSlugs = null): int
    {
        $query = ArticleModel::query()
            ->where('is_published', true);

        return QueryHelpers::applyTagFilter($query, $tagSlugs)->count();
    }

    public function findPublishedBySlug(string $slug): ?ArticleResponseDTO
    {
        $article = ArticleModel::query()
            ->with(['author', 'tags'])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        return $article ? $this->dtoFactory->createArticleDTO($article) : null;
    }

    public function searchPublished(string $query, int $limit = 12): array
    {
        $searchTerm = '%'.mb_strtolower($query).'%';

        $articles = ArticleModel::query()
            ->with(['author', 'tags'])
            ->where('is_published', true)
            ->where(function ($q) use ($searchTerm): void {
                $q->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(content) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(excerpt) LIKE ?', [$searchTerm]);
            })
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return $articles->map(fn (ArticleModel $article) => $this->dtoFactory->createArticleDTO($article))->all();
    }
}
