<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\DTOs\RoleResponseDTO;
use App\Application\Authorization\Services\RolePermissionGrantsInterface;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Domain\Authorization\Entities\Permission;
use App\Domain\Authorization\Repositories\PermissionRepositoryInterface;
use App\Domain\Authorization\ValueObjects\PermissionId;
use App\Domain\Authorization\ValueObjects\PermissionKey;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The role service writes the grant, its history entry and the cache invalidation; this
 * only finds the roles and makes sure the permission rows exist.
 */
final readonly class RolePermissionGrants implements RolePermissionGrantsInterface
{
    public function __construct(
        private PermissionRepositoryInterface $permissions,
        private RoleServiceInterface $roles,
    ) {}

    public function grantToRole(string $roleName, PermissionDefinitionDTO ...$permissions): void
    {
        $role = $this->roles->findByName($roleName);

        if (! $role instanceof RoleResponseDTO) {
            return;
        }

        $this->grant([$role->id], $permissions);
    }

    public function grantToRolesWith(string $heldPermissionKey, PermissionDefinitionDTO ...$permissions): void
    {
        /** @var list<string> $roleIds */
        $roleIds = RoleModel::query()
            ->whereHas('permissions', static fn (Builder $query): Builder => $query->where('key', $heldPermissionKey))
            ->orderBy('name')
            ->pluck('id')
            ->map(strval(...))
            ->all();

        $this->grant($roleIds, $permissions);
    }

    /**
     * @param  list<string>  $roleIds
     * @param  array<PermissionDefinitionDTO>  $permissions
     */
    private function grant(array $roleIds, array $permissions): void
    {
        if ($roleIds === [] || $permissions === []) {
            return;
        }

        DB::transaction(function () use ($roleIds, $permissions): void {
            $keys = array_map($this->ensureExists(...), array_values($permissions));

            foreach ($roleIds as $roleId) {
                $this->roles->assignPermissions($roleId, $keys);
            }
        });
    }

    private function ensureExists(PermissionDefinitionDTO $definition): string
    {
        $key = new PermissionKey($definition->key);

        if (! $this->permissions->findByKey($key) instanceof Permission) {
            $this->permissions->save(new Permission(
                id: PermissionId::generate(),
                key: $key,
                label: $definition->label,
                resource: $definition->resource,
                action: $definition->action,
                module: $definition->module,
            ));
        }

        return $key->value;
    }
}
