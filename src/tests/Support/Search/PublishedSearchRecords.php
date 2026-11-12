<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Records behind stored search documents of the core types, which check their results against them.
 * Created without model events, so no document of their own is stored.
 */
final class PublishedSearchRecords
{
    /**
     * The key of a new record of the core type, published or not; any other type gets a key of no record.
     */
    public static function sourceId(string $type, bool $published = true): string
    {
        $model = match ($type) {
            'events' => EventModel::class,
            'articles' => ArticleModel::class,
            'galleries' => GalleryModel::class,
            default => null,
        };

        if ($model === null) {
            return (string) Str::uuid();
        }

        $attributes = ['is_published' => $published];
        if ($model === ArticleModel::class && $published) {
            $attributes['published_at'] = now();
        }

        /** @var Model $record */
        $record = $model::withoutEvents(static fn (): Model => $model::factory()->create($attributes));

        return (string) $record->getKey();
    }
}
