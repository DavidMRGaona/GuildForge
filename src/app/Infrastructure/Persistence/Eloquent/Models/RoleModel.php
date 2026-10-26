<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property bool $is_protected
 * @property bool $requires_association_member
 * @property int|null $max_holders
 */
final class RoleModel extends Model
{
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    protected $table = 'roles';

    protected $fillable = [
        'id',
        'name',
        'display_name',
        'description',
        'is_protected',
        'requires_association_member',
        'max_holders',
    ];

    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
            'requires_association_member' => 'boolean',
            'max_holders' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<PermissionModel, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            PermissionModel::class,
            'role_permission',
            'role_id',
            'permission_id'
        )->withTimestamps();
    }

    /**
     * @return BelongsToMany<UserModel, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            UserModel::class,
            'user_role',
            'role_id',
            'user_id'
        )->withTimestamps();
    }

    /**
     * @return HasMany<RoleAuditEntryModel, $this>
     */
    public function auditEntries(): HasMany
    {
        return $this->hasMany(RoleAuditEntryModel::class, 'role_id');
    }
}
