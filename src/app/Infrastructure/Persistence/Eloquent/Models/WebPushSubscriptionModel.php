<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A browser that receives push messages for an account (core#803). Written only through
 * WebPushSubscriptionServiceInterface.
 *
 * @property string $id
 * @property string $user_id
 * @property string $endpoint
 * @property string $endpoint_hash
 * @property string $p256dh
 * @property string $auth
 * @property string $browser
 * @property string $platform
 * @property Carbon|null $last_used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class WebPushSubscriptionModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'web_push_subscriptions';

    protected $fillable = [
        'user_id',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth',
        'browser',
        'platform',
        'last_used_at',
        'created_at',
    ];

    protected $hidden = ['endpoint', 'p256dh', 'auth'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'p256dh' => 'encrypted',
            'auth' => 'encrypted',
            'last_used_at' => 'datetime',
        ];
    }
}
