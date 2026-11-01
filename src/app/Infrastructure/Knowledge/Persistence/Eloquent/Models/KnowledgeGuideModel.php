<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $guide_id
 * @property string $locale
 * @property string $source
 * @property string|null $module
 * @property string $audience
 * @property string $category_id
 * @property int $sort_order
 * @property string $title
 * @property string $summary
 * @property list<string> $keywords
 * @property string $body_format
 * @property string $body
 * @property string $body_text
 * @property array<string, mixed> $conditions
 * @property list<string> $related
 * @property bool $featured
 * @property string $content_hash
 * @property CarbonImmutable|null $first_seen_at
 * @property CarbonImmutable|null $content_changed_at
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class KnowledgeGuideModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'knowledge_guides';

    protected $fillable = [
        'id',
        'guide_id',
        'locale',
        'source',
        'module',
        'audience',
        'category_id',
        'sort_order',
        'title',
        'summary',
        'keywords',
        'body_format',
        'body',
        'body_text',
        'conditions',
        'related',
        'featured',
        'content_hash',
        'first_seen_at',
        'content_changed_at',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'keywords' => 'array',
            'conditions' => 'array',
            'related' => 'array',
            'featured' => 'boolean',
            'active' => 'boolean',
            'first_seen_at' => 'immutable_datetime',
            'content_changed_at' => 'immutable_datetime',
        ];
    }
}
