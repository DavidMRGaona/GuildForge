<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Sources;

use App\Application\Search\Contracts\ChecksSearchHits;
use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Search\Sources\Concerns\ChecksSearchableHits;
use App\Infrastructure\Search\Support\PlainText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class GallerySearchSource implements ChecksSearchHits, IndexedSearchSourceInterface
{
    use ChecksSearchableHits;

    public function type(): string
    {
        return 'galleries';
    }

    public function label(): string
    {
        return __('search.sources.galleries.label');
    }

    public function itemLabel(): string
    {
        return __('search.sources.galleries.item');
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

        $gallery = $this->searchable()->with(['photos', 'tags'])->whereKey($id)->first();

        return $gallery instanceof GalleryModel ? $this->toDocument($gallery) : null;
    }

    /**
     * @return iterable<SearchDocumentDTO>
     */
    public function documents(): iterable
    {
        foreach ($this->searchable()->with(['photos', 'tags'])->lazyById(100) as $gallery) {
            yield $this->toDocument($gallery);
        }
    }

    /**
     * The records that may be found: documents, full rebuilds and the check of results all read it.
     *
     * @return Builder<GalleryModel>
     */
    private function searchable(): Builder
    {
        return GalleryModel::query()->where('is_published', true);
    }

    private function toDocument(GalleryModel $gallery): SearchDocumentDTO
    {
        $parts = [PlainText::from($gallery->description ?? '')];

        foreach ($gallery->photos as $photo) {
            $parts[] = PlainText::from($photo->caption ?? '');
        }

        $keywords = $gallery->tags->pluck('name')->implode(' ');
        $cover = $gallery->photos->first();

        return new SearchDocumentDTO(
            id: $gallery->id,
            title: $gallery->title,
            body: implode("\n", array_filter($parts, static fn (string $part): bool => $part !== '')),
            url: route('galleries.show', $gallery->slug, false),
            keywords: $keywords !== '' ? $keywords : null,
            imagePublicId: $cover instanceof PhotoModel ? $cover->image_public_id : null,
        );
    }
}
