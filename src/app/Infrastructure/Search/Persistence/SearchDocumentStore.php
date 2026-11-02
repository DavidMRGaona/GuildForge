<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Persistence;

use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;

final class SearchDocumentStore implements SearchDocumentStoreInterface
{
    /** Length of the string columns that hold user-facing text (title, meta) */
    private const int STRING_LENGTH = 255;

    private const int CHUNK = 500;

    public function upsert(string $type, ?string $module, SearchDocumentDTO $doc): string
    {
        $model = SearchDocumentModel::query()->firstOrNew(['type' => $type, 'source_id' => $doc->id]);

        $model->fill([
            'module' => $module,
            'title' => mb_substr($doc->title, 0, self::STRING_LENGTH),
            'body' => $doc->body,
            'keywords' => $doc->keywords,
            'meta' => $doc->meta === null ? null : mb_substr($doc->meta, 0, self::STRING_LENGTH),
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
        $id = SearchDocumentModel::query()
            ->where('type', $type)
            ->where('source_id', $sourceId)
            ->value('id');

        if ($id === null) {
            return null;
        }

        $this->deleteIds([(string) $id]);

        return (string) $id;
    }

    public function moduleOf(string $type, string $sourceId): ?string
    {
        $module = SearchDocumentModel::query()
            ->where('type', $type)
            ->where('source_id', $sourceId)
            ->value('module');

        return is_string($module) ? $module : null;
    }

    public function deleteExcept(string $type, array $keepSourceIds): array
    {
        // The difference is computed here: a NOT IN with every kept id would exceed the bindings a query accepts
        $keep = array_flip($keepSourceIds);
        $ids = [];

        foreach (SearchDocumentModel::query()->where('type', $type)->toBase()->get(['id', 'source_id']) as $row) {
            if (! isset($keep[(string) $row->source_id])) {
                $ids[] = (string) $row->id;
            }
        }

        $this->deleteIds($ids);

        return $ids;
    }

    public function deleteType(string $type): array
    {
        $ids = array_values(SearchDocumentModel::query()
            ->where('type', $type)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all());

        $this->deleteIds($ids);

        return $ids;
    }

    public function deleteTypeOfDisabledModules(string $type, array $enabledModules): array
    {
        $ids = array_values(SearchDocumentModel::query()
            ->where('type', $type)
            ->whereNotNull('module')
            ->when($enabledModules !== [], static fn ($query) => $query->whereNotIn('module', $enabledModules))
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all());

        $this->deleteIds($ids);

        return $ids;
    }

    public function types(): array
    {
        return array_values(SearchDocumentModel::query()
            ->distinct()
            ->pluck('type')
            ->map(static fn (mixed $type): string => (string) $type)
            ->all());
    }

    /**
     * Through the query builder, so Scout never hears about it: the indexer mirrors the removal itself.
     *
     * @param  list<string>  $ids
     */
    private function deleteIds(array $ids): void
    {
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            SearchDocumentModel::query()->whereIn('id', $chunk)->delete();
        }
    }
}
