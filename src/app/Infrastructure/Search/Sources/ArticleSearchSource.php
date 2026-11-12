<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Sources;

use App\Application\Search\Contracts\ChecksSearchHits;
use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Search\Sources\Concerns\ChecksPublishedHits;
use App\Infrastructure\Search\Support\PlainText;
use Illuminate\Support\Str;

final class ArticleSearchSource implements ChecksSearchHits, IndexedSearchSourceInterface
{
    use ChecksPublishedHits;

    public function type(): string
    {
        return 'articles';
    }

    public function label(): string
    {
        return __('search.sources.articles.label');
    }

    public function itemLabel(): string
    {
        return __('search.sources.articles.item');
    }

    public function pastLabel(): ?string
    {
        return null;
    }

    public function document(string $id): ?SearchDocumentDTO
    {
        // Keys are uuids: anything else matches no row, and PostgreSQL rejects it in the query
        if (! Str::isUuid($id)) {
            return null;
        }

        $article = ArticleModel::query()->with('tags')->whereKey($id)->where('is_published', true)->first();

        return $article instanceof ArticleModel ? $this->toDocument($article) : null;
    }

    /**
     * @return iterable<SearchDocumentDTO>
     */
    public function documents(): iterable
    {
        foreach (ArticleModel::query()->with('tags')->where('is_published', true)->lazyById(200) as $article) {
            yield $this->toDocument($article);
        }
    }

    /**
     * @return class-string<ArticleModel>
     */
    private function searchedModel(): string
    {
        return ArticleModel::class;
    }

    private function toDocument(ArticleModel $article): SearchDocumentDTO
    {
        $keywords = $article->tags->pluck('name')->implode(' ');

        return new SearchDocumentDTO(
            id: $article->id,
            title: $article->title,
            body: PlainText::from(($article->excerpt ?? '').' '.$article->content),
            url: route('articles.show', $article->slug, false),
            keywords: $keywords !== '' ? $keywords : null,
            imagePublicId: $article->featured_image_public_id,
            publishedAt: $article->published_at?->toImmutable(),
        );
    }
}
