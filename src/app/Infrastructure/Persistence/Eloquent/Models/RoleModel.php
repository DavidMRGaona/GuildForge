<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property bool $is_protected
 */
final class RoleModel extends Model
{
    use HasUuids;

    protected $table = 'roles';

    protected $fillable = [
        'id',
        'name',
        'display_name',
        'description',
        'is_protected',
    ];

    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
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
}
