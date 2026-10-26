<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\DTOs\CreateRoleDTO;
use App\Application\Authorization\DTOs\RoleResponseDTO;
use App\Application\Authorization\DTOs\UpdateRoleDTO;
use App\Application\Authorization\Services\AssociationMemberRoleRule;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\BaseRoleRule;
use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Application\Authorization\Services\RoleDefinitionChanges;
use App\Application\Authorization\Services\RoleRestrictionRule;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Domain\Authorization\Entities\Role;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Domain\Authorization\Exceptions\RoleNotFoundException;
use App\Domain\Authorization\Exceptions\RoleProtectedException;
use App\Domain\Authorization\Repositories\PermissionRepositoryInterface;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\Authorization\ValueObjects\RoleId;
use App\Domain\Authorization\ValueObjects\RoleName;
use Illuminate\Support\Facades\DB;

final readonly class RoleService implements RoleServiceInterface
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository,
        private AuthorizationServiceInterface $authorization,
        private AssociationMemberRoleRule $associationMemberRole,
        private BaseRoleRule $baseRole,
        private RoleRestrictionRule $roleRestriction,
        private RoleAuditLoggerInterface $audit,
        private RoleDefinitionChanges $definitionChanges = new RoleDefinitionChanges,
    ) {}

    /**
     * @return array<RoleResponseDTO>
     */
    public function all(): array
    {
        $roles = $this->roleRepository->findAll();

        return array_map(
            $this->toResponseDTO(...),
            $roles
        );
    }

    public function findById(string $id): ?RoleResponseDTO
    {
        $role = $this->roleRepository->findById(RoleId::fromString($id));

        if (! $role instanceof Role) {
            return null;
        }

        return $this->toResponseDTO($role);
    }

    public function findByName(string $name): ?RoleResponseDTO
    {
        $role = $this->roleRepository->findByName(RoleName::fromString($name));

        if (! $role instanceof Role) {
            return null;
        }

        return $this->toResponseDTO($role);
    }

    public function create(CreateRoleDTO $dto): RoleResponseDTO
    {
        $role = new Role(
            id: RoleId::generate(),
            name: new RoleName($dto->name),
            displayName: $dto->displayName,
            description: $dto->description,
            isProtected: $dto->isProtected,
        );

        $this->guardRestrictions($role);

        // The role and its history entry stand or fall together
        DB::transaction(function () use ($role, $dto): void {
            $this->roleRepository->save($role);

            // Assign permissions if provided
            if (count($dto->permissionKeys) > 0) {
                $this->writePermissions($role->id(), $dto->permissionKeys, detach: true);
            }

            $this->audit->roleChanged($role->id()->value, $role->name()->value, RoleAuditEvent::RoleCreated, $this->definitionChanges->between(
                [],
                $this->roleRepository->getPermissionKeys($role->id()),
                [],
                $this->fieldsOf($role),
            ));
        });

        if (count($dto->permissionKeys) > 0) {
            $this->authorization->invalidateCache();
        }

        return $this->toResponseDTO($role);
    }

    public function update(string $id, UpdateRoleDTO $dto): RoleResponseDTO
    {
        $existingRole = $this->roleRepository->findById(RoleId::fromString($id));

        if (! $existingRole instanceof Role) {
            throw RoleNotFoundException::withId($id);
        }

        $role = new Role(
            id: $existingRole->id(),
            name: $existingRole->name(),
            displayName: $dto->displayName ?? $existingRole->displayName(),
            description: $dto->description ?? $existingRole->description(),
            isProtected: $existingRole->isProtected(),
            requiresAssociationMember: $existingRole->requiresAssociationMember(),
            maxHolders: $existingRole->maxHolders(),
        );

        $this->guardRestrictions($role);

        DB::transaction(function () use ($role, $dto): void {
            $this->roleRepository->save($role);

            // Sync permissions if provided (null means don't update)
            if ($dto->permissionKeys !== null) {
                $this->changePermissions($role, $dto->permissionKeys, detach: true);
            }
        });

        if ($dto->permissionKeys !== null) {
            $this->authorization->invalidateCache();
        }

        return $this->toResponseDTO($role);
    }

    public function delete(string $id): void
    {
        $role = $this->roleRepository->findById(RoleId::fromString($id));

        if (! $role instanceof Role) {
            throw RoleNotFoundException::withId($id);
        }

        if ($role->isProtected()) {
            throw RoleProtectedException::cannotDelete($role->name()->value);
        }

        if ($this->baseRole->isBaseRoleName($role->name()->value)) {
            throw RoleProtectedException::cannotDeleteBaseRole($role->name()->value);
        }

        if ($this->associationMemberRole->isAssociationMemberRoleName($role->name()->value)) {
            throw RoleProtectedException::cannotDeleteAssociationMemberRole($role->name()->value);
        }

        DB::transaction(function () use ($role): void {
            // Before deleting: the history also records the role taken from each holder
            $this->audit->roleChanged($role->id()->value, $role->name()->value, RoleAuditEvent::RoleDeleted, $this->definitionChanges->between(
                $this->roleRepository->getPermissionKeys($role->id()),
                [],
            ));
            $this->roleRepository->delete($role->id());
        });

        $this->authorization->invalidateCache();
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    public function assignPermissions(string $roleId, array $permissionKeys): void
    {
        $this->changePermissionsOf($roleId, $permissionKeys, detach: false);
        $this->authorization->invalidateCache();
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    public function syncPermissions(string $roleId, array $permissionKeys): void
    {
        $this->changePermissionsOf($roleId, $permissionKeys, detach: true);
        $this->authorization->invalidateCache();
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    private function changePermissionsOf(string $roleId, array $permissionKeys, bool $detach): void
    {
        $role = $this->roleRepository->findById(RoleId::fromString($roleId));

        if (! $role instanceof Role) {
            // Nothing to change: the repository leaves an unknown role alone too
            return;
        }

        DB::transaction(fn () => $this->changePermissions($role, $permissionKeys, $detach));
    }

    /**
     * Writes the permissions and, when they changed, the history entry. Run it in a transaction.
     *
     * @param  array<string>  $permissionKeys
     */
    private function changePermissions(Role $role, array $permissionKeys, bool $detach): void
    {
        $before = $this->roleRepository->getPermissionKeys($role->id());
        $this->writePermissions($role->id(), $permissionKeys, $detach);
        $changes = $this->definitionChanges->between($before, $this->roleRepository->getPermissionKeys($role->id()));

        if ($changes !== []) {
            $this->audit->roleChanged($role->id()->value, $role->name()->value, RoleAuditEvent::RoleUpdated, $changes);
        }
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    private function writePermissions(RoleId $roleId, array $permissionKeys, bool $detach): void
    {
        $permissionIds = $this->permissionRepository->findIdsByKeys($permissionKeys);

        if ($detach) {
            $this->roleRepository->syncPermissions($roleId, $permissionIds);
        } else {
            $this->roleRepository->attachPermissions($roleId, $permissionIds);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldsOf(Role $role): array
    {
        return [
            'name' => $role->name()->value,
            'requires_association_member' => $role->requiresAssociationMember(),
            'max_holders' => $role->maxHolders(),
        ];
    }

    private function guardRestrictions(Role $role): void
    {
        $restricted = $role->requiresAssociationMember() || $role->maxHolders() !== null;

        if ($restricted && ! $this->roleRestriction->canBeRestricted($role->name()->value)) {
            throw RoleProtectedException::cannotRestrict($role->name()->value);
        }
    }

    private function toResponseDTO(Role $role): RoleResponseDTO
    {
        $permissionKeys = $this->roleRepository->getPermissionKeys($role->id());

        return new RoleResponseDTO(
            id: $role->id()->value,
            name: $role->name()->value,
            displayName: $role->displayName(),
            description: $role->description(),
            isProtected: $role->isProtected(),
            permissionKeys: $permissionKeys,
        );
    }
}
