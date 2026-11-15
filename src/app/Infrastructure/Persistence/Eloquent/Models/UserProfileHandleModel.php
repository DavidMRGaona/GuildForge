<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A profile address no longer in use (core#740). Reserved for ever; `handle` and `user_id` are null once
 * the account was anonymized or deleted.
 *
 * @property string $handle_hash
 * @property string|null $handle
 * @property string|null $user_id
 * @property Carbon $retired_at
 */
final class UserProfileHandleModel extends Model
{
    use StoresInstantsInStorageTimezone;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'user_profile_handles';

    protected $primaryKey = 'handle_hash';

    protected $keyType = 'string';

    protected $fillable = [
        'handle_hash',
        'handle',
        'user_id',
        'retired_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retired_at' => 'datetime',
        ];
    }
}
