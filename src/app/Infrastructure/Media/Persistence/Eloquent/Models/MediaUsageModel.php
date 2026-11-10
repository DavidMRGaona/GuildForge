<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $file_id
 * @property string $source_type
 * @property string|null $module
 * @property string $record_id
 * @property string $role
 * @property string $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class MediaUsageModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'media_usages';

    protected $fillable = [
        'id',
        'file_id',
        'source_type',
        'module',
        'record_id',
        'role',
        'title',
    ];

    /**
     * @return BelongsTo<MediaFileModel, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaFileModel::class, 'file_id', 'file_id');
    }
}
