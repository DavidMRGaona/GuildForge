<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\DTOs\CreateRoleDTO;
use App\Application\Authorization\DTOs\RoleResponseDTO;
use App\Application\Authorization\DTOs\UpdateRoleDTO;
use App\Application\Authorization\Services\AdminOnlyPermissionRule;
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
use App\Domain\Events\RolePermissionsChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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
        private AdminOnlyPermissionRule $adminOnly,
        private RoleDefinitionChanges $definitionChanges = new RoleDefinitionChanges,
        private RoleChangeAnnouncer $announcer = new RoleChangeAnnouncer,
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
        $this->guardAdminOnlyPermissions($role, $dto->permissionKeys);

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
        $this->guardAdminOnlyPermissions($role, $dto->permissionKeys ?? []);

        $changed = null;

        DB::transaction(function () use ($role, $dto, &$changed): void {
            $this->roleRepository->save($role);

            // Sync permissions if provided (null means don't update)
            if ($dto->permissionKeys !== null) {
                $changed = $this->changePermissions($role, $dto->permissionKeys, detach: true);
            }
        });

        if ($dto->permissionKeys !== null) {
            $this->authorization->invalidateCache();
            $this->announce($changed);
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
            $permissionKeys = $this->roleRepository->getPermissionKeys($role->id());

            // Before deleting: the history also records the role taken from each holder, and
            // the pivot rows with the holders go with the role
            $event = $this->announcer->captureDeletion($role->id()->value, $role->name()->value, array_values($permissionKeys));
            $this->audit->roleChanged($role->id()->value, $role->name()->value, RoleAuditEvent::RoleDeleted, $this->definitionChanges->between(
                $permissionKeys,
                [],
            ));
            $this->roleRepository->delete($role->id());
            // Registered before the announcement: the cache is fresh when a listener runs
            $this->authorization->invalidateCache();
            $this->announcer->announceDeletion($event);
        });
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    public function assignPermissions(string $roleId, array $permissionKeys): void
    {
        $changed = $this->changePermissionsOf($roleId, $permissionKeys, detach: false);
        $this->authorization->invalidateCache();
        $this->announce($changed);
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    public function syncPermissions(string $roleId, array $permissionKeys): void
    {
        $changed = $this->changePermissionsOf($roleId, $permissionKeys, detach: true);
        $this->authorization->invalidateCache();
        $this->announce($changed);
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    private function changePermissionsOf(string $roleId, array $permissionKeys, bool $detach): ?RolePermissionsChanged
    {
        $role = $this->roleRepository->findById(RoleId::fromString($roleId));

        if (! $role instanceof Role) {
            // Nothing to change: the repository leaves an unknown role alone too
            return null;
        }

        $this->guardAdminOnlyPermissions($role, $permissionKeys);

        return DB::transaction(fn (): ?RolePermissionsChanged => $this->changePermissions($role, $permissionKeys, $detach));
    }

    /**
     * After the commit, and after the cache invalidation registered before it, so a listener
     * never reads stale permissions.
     */
    private function announce(?RolePermissionsChanged $event): void
    {
        if (! $event instanceof RolePermissionsChanged) {
            return;
        }

        DB::afterCommit(static function () use ($event): void {
            Event::dispatch($event);
        });
    }

    /**
     * Writes the permissions and, when they changed, the history entry. Run it in a transaction.
     * Returns the event to announce once the change is out, or null when nothing changed.
     *
     * @param  array<string>  $permissionKeys
     */
    private function changePermissions(Role $role, array $permissionKeys, bool $detach): ?RolePermissionsChanged
    {
        $before = $this->roleRepository->getPermissionKeys($role->id());
        $this->writePermissions($role->id(), $permissionKeys, $detach);
        $after = $this->roleRepository->getPermissionKeys($role->id());
        $changes = $this->definitionChanges->between($before, $after);

        if ($changes === []) {
            return null;
        }

        $this->audit->roleChanged($role->id()->value, $role->name()->value, RoleAuditEvent::RoleUpdated, $changes);

        return new RolePermissionsChanged(
            $role->id()->value,
            $role->name()->value,
            $this->sortedKeys($before),
            $this->sortedKeys($after),
        );
    }

    /**
     * @param  array<string>  $keys
     * @return list<string>
     */
    private function sortedKeys(array $keys): array
    {
        $sorted = array_values(array_map(strval(...), $keys));
        sort($sorted);

        return $sorted;
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

    /**
     * Installing modules and applying updates stay with the administrator role, whoever asks.
     *
     * @param  array<string>  $permissionKeys
     */
    private function guardAdminOnlyPermissions(Role $role, array $permissionKeys): void
    {
        $notAllowed = $this->adminOnly->notAllowedFor($role->name()->value, $permissionKeys);

        if ($notAllowed !== []) {
            throw RoleProtectedException::adminOnlyPermissions($role->name()->value, $notAllowed);
        }
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
