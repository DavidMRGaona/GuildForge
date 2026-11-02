<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Persistence;

use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Illuminate\Database\Eloquent\Builder;

final class SearchDocumentStore implements SearchDocumentStoreInterface
{
    public function upsert(string $type, ?string $module, SearchDocumentDTO $doc): string
    {
        $model = SearchDocumentModel::query()->firstOrNew(['type' => $type, 'source_id' => $doc->id]);

        $model->fill([
            'module' => $module,
            'title' => $doc->title,
            'body' => $doc->body,
            'keywords' => $doc->keywords,
            'meta' => $doc->meta,
            'url' => $doc->url,
            'image_public_id' => $doc->imagePublicId,
            'starts_at' => $doc->startsAt,
            'ends_at' => $doc->endsAt,
            'published_at' => $doc->publishedAt,
            'finished' => $doc->finished,
            'audience' => $doc->audience->value,
            'locale' => $doc->locale ?? app()->getLocale(),
        ]);
        $model->save();

        return $model->id;
    }

    public function delete(string $type, string $sourceId): ?string
    {
        $model = SearchDocumentModel::query()
            ->where('type', $type)
            ->where('source_id', $sourceId)
            ->first();

        if ($model === null) {
            return null;
        }

        $model->delete();

        return $model->id;
    }

    public function deleteExcept(string $type, array $keepSourceIds): array
    {
        return $this->deleteWhere(
            SearchDocumentModel::query()
                ->where('type', $type)
                ->whereNotIn('source_id', $keepSourceIds),
        );
    }

    public function deleteType(string $type): array
    {
        return $this->deleteWhere(SearchDocumentModel::query()->where('type', $type));
    }

    /**
     * @param  Builder<SearchDocumentModel>  $query
     * @return list<string>
     */
    private function deleteWhere(Builder $query): array
    {
        $ids = array_values($query->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all());

        foreach (array_chunk($ids, 500) as $chunk) {
            SearchDocumentModel::query()->whereIn('id', $chunk)->delete();
        }

        return $ids;
    }
}
