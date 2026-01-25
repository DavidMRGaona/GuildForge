<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $key
 * @property string $label
 * @property string $resource
 * @property string $action
 * @property string|null $module
 */
final class PermissionModel extends Model
{
    use HasUuids;

    protected $table = 'permissions';

    protected $fillable = [
        'id',
        'key',
        'label',
        'resource',
        'action',
        'module',
    ];

    /**
     * @return BelongsToMany<RoleModel, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            RoleModel::class,
            'role_permission',
            'permission_id',
            'role_id'
        )->withTimestamps();
    }
}
