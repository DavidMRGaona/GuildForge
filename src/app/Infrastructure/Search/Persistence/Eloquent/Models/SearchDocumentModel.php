<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property string $id
 * @property string $type
 * @property string $source_id
 * @property string|null $module
 * @property string $title
 * @property string $body
 * @property string|null $keywords
 * @property string|null $meta
 * @property string $url
 * @property string|null $image_public_id
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $published_at
 * @property bool $finished
 * @property string $audience
 * @property string $locale
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class SearchDocumentModel extends Model
{
    use HasUuids;
    use Searchable;
    use StoresInstantsInStorageTimezone;

    protected $table = 'search_documents';

    protected $fillable = [
        'id',
        'type',
        'source_id',
        'module',
        'title',
        'body',
        'keywords',
        'meta',
        'url',
        'image_public_id',
        'starts_at',
        'ends_at',
        'published_at',
        'finished',
        'audience',
        'locale',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'finished' => 'boolean',
        ];
    }

    /**
     * The index name; Scout adds the configured prefix.
     */
    public function searchableAs(): string
    {
        return 'search_documents';
    }

    /**
     * The url and the image stay out of the index: the engine only ranks, the rows are read from the database.
     *
     * @return array{id: string, type: string, title: string, keywords: string|null, meta: string|null, body: string, audience: string, locale: string, finished: bool, ends_at_ts: int|null}
     */
    public function toSearchableArray(): array
    {
        $end = $this->ends_at ?? $this->starts_at;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'keywords' => $this->keywords,
            'meta' => $this->meta,
            'body' => $this->body,
            'audience' => $this->audience,
            'locale' => $this->locale,
            'finished' => $this->finished,
            'ends_at_ts' => $end?->getTimestamp(),
        ];
    }
}
