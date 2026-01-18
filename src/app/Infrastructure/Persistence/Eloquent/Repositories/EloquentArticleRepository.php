<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Entities\Article;
use App\Domain\Repositories\ArticleRepositoryInterface;
use App\Domain\ValueObjects\ArticleId;
use App\Domain\ValueObjects\Slug;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final readonly class EloquentArticleRepository implements ArticleRepositoryInterface
{
    public function findById(ArticleId $id): ?Article
    {
        $model = ArticleModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findBySlug(string $slug): ?Article
    {
        $model = ArticleModel::query()->where('slug', $slug)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findPublished(): Collection
    {
        return ArticleModel::query()
            ->where('is_published', true)
            ->orderBy('published_at', 'desc')
            ->get()
            ->map(fn (ArticleModel $model): Article => $this->toDomain($model));
    }

    public function findByAuthor(int $authorId): Collection
    {
        return ArticleModel::query()
            ->where('author_id', $authorId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (ArticleModel $model): Article => $this->toDomain($model));
    }

    public function save(Article $article): void
    {
        ArticleModel::query()->updateOrCreate(
            ['id' => $article->id()->value],
            $this->toArray($article),
        );
    }

    public function delete(Article $article): void
    {
        ArticleModel::query()->where('id', $article->id()->value)->delete();
    }

    private function toDomain(ArticleModel $model): Article
    {
        return new Article(
            id: new ArticleId($model->id),
            title: $model->title,
            slug: new Slug($model->slug),
            content: $model->content,
            authorId: $model->author_id,
            excerpt: $model->excerpt,
            featuredImagePublicId: $model->featured_image_public_id,
            isPublished: $model->is_published,
            publishedAt: $model->published_at !== null
                ? new DateTimeImmutable($model->published_at->toDateTimeString())
                : null,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at !== null
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Article $article): array
    {
        return [
            'id' => $article->id()->value,
            'title' => $article->title(),
            'slug' => $article->slug()->value,
            'content' => $article->content(),
            'excerpt' => $article->excerpt(),
            'featured_image_public_id' => $article->featuredImagePublicId(),
            'is_published' => $article->isPublished(),
            'published_at' => $article->publishedAt()?->format('Y-m-d H:i:s'),
            'author_id' => $article->authorId(),
        ];
    }
}
