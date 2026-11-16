<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Two-factor authentication of one account (core#768). Written only through
 * TwoFactorServiceInterface; the secret and the recovery code hashes are encrypted at rest and
 * hidden from serialization.
 *
 * @property string $user_id
 * @property string $secret Base32
 * @property Carbon|null $confirmed_at
 * @property list<string>|null $recovery_codes SHA-256 hashes of the unused codes
 * @property int|null $last_used_timestep
 * @property string|null $session_seal what a session must hold to stay signed in (TwoFactorSessionSeal)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class UserTwoFactorModel extends Model
{
    use StoresInstantsInStorageTimezone;

    protected $table = 'user_two_factor';

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'secret',
        'confirmed_at',
        'recovery_codes',
        'last_used_timestep',
        'session_seal',
    ];

    protected $hidden = [
        'secret',
        'recovery_codes',
        'session_seal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'confirmed_at' => 'datetime',
            'recovery_codes' => 'encrypted:array',
            'last_used_timestep' => 'integer',
        ];
    }
}
