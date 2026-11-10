<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Permanent record of files deleted from the library; rows are never removed.
 *
 * @property string $id
 * @property string $file_id
 * @property string $resource_type
 * @property string $folder
 * @property string|null $source_type
 * @property int|null $bytes
 * @property int|null $width
 * @property int|null $height
 * @property string $outcome
 * @property string|null $deleted_by
 * @property Carbon $deleted_at
 */
final class MediaDeletionModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    public $timestamps = false;

    protected $table = 'media_deletions';

    protected $fillable = [
        'id',
        'file_id',
        'resource_type',
        'folder',
        'source_type',
        'bytes',
        'width',
        'height',
        'outcome',
        'deleted_by',
        'deleted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'deleted_by');
    }
}
