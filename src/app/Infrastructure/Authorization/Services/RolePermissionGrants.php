<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\DTOs\RoleResponseDTO;
use App\Application\Authorization\Services\AdminOnlyPermissionRule;
use App\Application\Authorization\Services\RolePermissionGrantsInterface;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Domain\Authorization\Entities\Permission;
use App\Domain\Authorization\Repositories\PermissionRepositoryInterface;
use App\Domain\Authorization\ValueObjects\PermissionId;
use App\Domain\Authorization\ValueObjects\PermissionKey;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The role service writes the grant, its history entry and the cache invalidation; this
 * only finds the roles and makes sure the permission rows exist.
 */
final readonly class RolePermissionGrants implements RolePermissionGrantsInterface
{
    public function __construct(
        private PermissionRepositoryInterface $permissions,
        private RoleServiceInterface $roles,
        private AdminOnlyPermissionRule $adminOnly,
    ) {}

    public function grantToRole(string $roleName, PermissionDefinitionDTO ...$permissions): void
    {
        $role = $this->roles->findByName($roleName);

        if (! $role instanceof RoleResponseDTO) {
            return;
        }

        $this->grant([$role->id => $role->name], $permissions);
    }

    public function grantToRolesWith(string $heldPermissionKey, PermissionDefinitionDTO ...$permissions): void
    {
        /** @var array<string, string> $roles */
        $roles = RoleModel::query()
            ->whereHas('permissions', static fn (Builder $query): Builder => $query->where('key', $heldPermissionKey))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->map(strval(...))
            ->all();

        $this->grant($roles, $permissions);
    }

    /**
     * @param  array<string, string>  $roles  role id => role name
     * @param  array<PermissionDefinitionDTO>  $permissions
     */
    private function grant(array $roles, array $permissions): void
    {
        if ($roles === [] || $permissions === []) {
            return;
        }

        DB::transaction(function () use ($roles, $permissions): void {
            $keys = array_map($this->ensureExists(...), array_values($permissions));

            foreach ($roles as $roleId => $roleName) {
                $this->roles->assignPermissions((string) $roleId, $this->allowedFor((string) $roleName, $keys));
            }
        });
    }

    /**
     * An admin-only permission is never granted to another role: the migration goes on without
     * it, and the log says what was left out.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function allowedFor(string $roleName, array $keys): array
    {
        $notAllowed = $this->adminOnly->notAllowedFor($roleName, $keys);

        if ($notAllowed !== []) {
            Log::warning('Admin-only permissions were not granted to a role other than admin.', [
                'role' => $roleName,
                'permissions' => $notAllowed,
            ]);
        }

        return array_values(array_diff($keys, $notAllowed));
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
