<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry of the role history. Entries are only ever added: nothing updates or deletes
 * them. The ids are UUIDv7, so entries written in the same second keep their order by id.
 *
 * @property string $id
 * @property string|null $user_id
 * @property string|null $role_id
 * @property string $role_name
 * @property string $event
 * @property array{permissions_added?: list<string>, permissions_removed?: list<string>, fields?: array<string, array{from: mixed, to: mixed}>}|null $changes
 * @property string|null $actor_id
 * @property string $source
 * @property Carbon $created_at
 */
final class RoleAuditEntryModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    public const UPDATED_AT = null;

    protected $table = 'role_audit_log';

    protected $fillable = [
        'user_id',
        'role_id',
        'role_name',
        'event',
        'changes',
        'actor_id',
        'source',
    ];

    /**
     * The account that made the change; trashed and anonymized accounts stay readable.
     *
     * @return BelongsTo<UserModel, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'actor_id')->withTrashed();
    }

    /**
     * The account that received or lost the role.
     *
     * @return BelongsTo<UserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id')->withTrashed();
    }

    /**
     * The role while it still exists; entries keep its name once it is deleted.
     *
     * @return BelongsTo<RoleModel, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(RoleModel::class, 'role_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }
}
