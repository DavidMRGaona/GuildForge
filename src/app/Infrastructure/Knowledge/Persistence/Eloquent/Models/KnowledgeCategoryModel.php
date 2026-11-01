<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $category_id
 * @property string|null $module
 * @property string $audience
 * @property int $sort_order
 * @property string $icon
 * @property string $label_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class KnowledgeCategoryModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'knowledge_categories';

    protected $fillable = [
        'id',
        'category_id',
        'module',
        'audience',
        'sort_order',
        'icon',
        'label_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
