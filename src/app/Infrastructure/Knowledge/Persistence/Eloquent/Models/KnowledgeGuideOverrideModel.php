<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $guide_id
 * @property bool $hidden
 * @property bool|null $featured null keeps what the guide file says
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class KnowledgeGuideOverrideModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'knowledge_guide_overrides';

    protected $fillable = [
        'id',
        'guide_id',
        'hidden',
        'featured',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hidden' => 'boolean',
            'featured' => 'boolean',
        ];
    }
}
