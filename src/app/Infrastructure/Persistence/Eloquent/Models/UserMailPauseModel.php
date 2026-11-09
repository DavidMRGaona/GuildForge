<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $user_id
 * @property Carbon|null $paused_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class UserMailPauseModel extends Model
{
    use StoresInstantsInStorageTimezone;

    protected $table = 'user_mail_pauses';

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'paused_until',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paused_until' => 'datetime',
        ];
    }
}
