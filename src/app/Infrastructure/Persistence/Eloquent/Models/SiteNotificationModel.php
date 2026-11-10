<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $category
 * @property string $title
 * @property string|null $body
 * @property string $url
 * @property string|null $icon
 * @property string|null $group_key
 * @property int $count
 * @property list<string>|null $actor_ids
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property Carbon $notified_at
 * @property int $sort_key
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class SiteNotificationModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'site_notifications';

    protected $fillable = [
        'user_id',
        'category',
        'title',
        'body',
        'url',
        'icon',
        'group_key',
        'count',
        'actor_ids',
        'subject_type',
        'subject_id',
        'notified_at',
        'sort_key',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'sort_key' => 'integer',
            'actor_ids' => 'array',
            'notified_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }
}
