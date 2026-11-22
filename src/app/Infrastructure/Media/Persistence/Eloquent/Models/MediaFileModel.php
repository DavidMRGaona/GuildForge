<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $file_id
 * @property string $resource_type
 * @property string $folder
 * @property string|null $format
 * @property int $bytes
 * @property int|null $width
 * @property int|null $height
 * @property Carbon $uploaded_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class MediaFileModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'media_files';

    protected $fillable = [
        'id',
        'file_id',
        'resource_type',
        'folder',
        'format',
        'bytes',
        'width',
        'height',
        'uploaded_at',
        'last_seen_at',
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
            'uploaded_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MediaUsageModel, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsageModel::class, 'file_id', 'file_id');
    }

    /**
     * Whether a source keeps the image out of sight in one of its usages (core#830): the library shows a
     * placeholder then, and gives no link to the file. Reads the loaded usages when there are.
     */
    public function hasHiddenUsage(): bool
    {
        if ($this->relationLoaded('usages')) {
            return $this->usages->contains(static fn (MediaUsageModel $usage): bool => $usage->is_hidden);
        }

        return $this->usages()->where('is_hidden', true)->exists();
    }
}
