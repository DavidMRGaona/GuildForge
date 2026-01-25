<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Article;
use App\Domain\ValueObjects\ArticleId;
use Illuminate\Support\Collection;

interface ArticleRepositoryInterface
{
    public function findById(ArticleId $id): ?Article;

    public function findBySlug(string $slug): ?Article;

    /**
     * @return Collection<int, Article>
     */
    public function findPublished(): Collection;

    /**
     * @return Collection<int, Article>
     */
    public function findByAuthor(string $authorId): Collection;

    public function save(Article $article): void;

    public function delete(Article $article): void;
}
