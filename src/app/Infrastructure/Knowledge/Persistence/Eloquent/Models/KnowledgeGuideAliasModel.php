<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $alias_id
 * @property string $guide_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class KnowledgeGuideAliasModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'knowledge_guide_aliases';

    protected $fillable = [
        'id',
        'alias_id',
        'guide_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [

        ];
    }
}
