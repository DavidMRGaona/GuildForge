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
 * @property string $token_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class CalendarFeedTokenModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'calendar_feed_tokens';

    protected $fillable = [
        'user_id',
        'token_hash',
    ];

    protected $hidden = [
        'token_hash',
    ];
}
