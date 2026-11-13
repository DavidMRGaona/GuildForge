<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One impersonation: who, whom, in which mode and from where, when it started and when and
 * why it ended. Written only through ImpersonationLoggerInterface; never deleted.
 *
 * @property string $id
 * @property string $impersonator_id
 * @property string $target_id
 * @property ImpersonationMode $mode
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property ImpersonationEndReason|null $ended_reason
 * @property string|null $ip
 * @property string|null $user_agent
 */
final class ImpersonationLogEntryModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    public $timestamps = false;

    protected $table = 'impersonation_log';

    protected $fillable = [
        'impersonator_id',
        'target_id',
        'mode',
        'started_at',
        'ended_at',
        'ended_reason',
        'ip',
        'user_agent',
    ];

    /**
     * Trashed and anonymized accounts stay readable.
     *
     * @return BelongsTo<UserModel, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'impersonator_id')->withTrashed();
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'target_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => ImpersonationMode::class,
            'ended_reason' => ImpersonationEndReason::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
