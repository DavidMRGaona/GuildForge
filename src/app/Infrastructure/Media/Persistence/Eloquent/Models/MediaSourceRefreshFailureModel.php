<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A media source type whose last refresh of a record failed (core#844); written and cleared only by
 * MediaSourceSyncState.
 *
 * @property string $source_type
 * @property string $token
 * @property Carbon $failed_at
 */
final class MediaSourceRefreshFailureModel extends Model
{
    use StoresInstantsInStorageTimezone;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'media_source_refresh_failures';

    protected $primaryKey = 'source_type';

    protected $keyType = 'string';

    protected $fillable = [
        'source_type',
        'token',
        'failed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }
}
