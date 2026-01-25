<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\DTOs\CreateRoleDTO;
use App\Application\Authorization\DTOs\RoleResponseDTO;
use App\Application\Authorization\DTOs\UpdateRoleDTO;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Domain\Authorization\Entities\Role;
use App\Domain\Authorization\Exceptions\RoleNotFoundException;
use App\Domain\Authorization\Exceptions\RoleProtectedException;
use App\Domain\Authorization\Repositories\PermissionRepositoryInterface;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\Authorization\ValueObjects\RoleId;
use App\Domain\Authorization\ValueObjects\RoleName;

final readonly class RoleService implements RoleServiceInterface
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository,
    ) {
    }

    /**
     * @return array<RoleResponseDTO>
     */
    public function all(): array
    {
        $roles = $this->roleRepository->findAll();

        return array_map(
            fn (Role $role): RoleResponseDTO => $this->toResponseDTO($role),
            $roles
        );
    }

    public function findById(string $id): ?RoleResponseDTO
    {
        $role = $this->roleRepository->findById(RoleId::fromString($id));

        if ($role === null) {
            return null;
        }

        return $this->toResponseDTO($role);
    }

    public function findByName(string $name): ?RoleResponseDTO
    {
        $role = $this->roleRepository->findByName(RoleName::fromString($name));

        if ($role === null) {
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

        $this->roleRepository->save($role);

        // Assign permissions if provided
        if (count($dto->permissionKeys) > 0) {
            $this->syncPermissions($role->id()->value, $dto->permissionKeys);
        }

        return $this->toResponseDTO($role);
    }

    public function update(string $id, UpdateRoleDTO $dto): RoleResponseDTO
    {
        $existingRole = $this->roleRepository->findById(RoleId::fromString($id));

        if ($existingRole === null) {
            throw RoleNotFoundException::withId($id);
        }

        $role = new Role(
            id: $existingRole->id(),
            name: $existingRole->name(),
            displayName: $dto->displayName ?? $existingRole->displayName(),
            description: $dto->description ?? $existingRole->description(),
            isProtected: $existingRole->isProtected(),
        );

        $this->roleRepository->save($role);

        // Sync permissions if provided (null means don't update)
        if ($dto->permissionKeys !== null) {
            $this->syncPermissions($role->id()->value, $dto->permissionKeys);
        }

        return $this->toResponseDTO($role);
    }

    public function delete(string $id): void
    {
        $role = $this->roleRepository->findById(RoleId::fromString($id));

        if ($role === null) {
            throw RoleNotFoundException::withId($id);
        }

        if ($role->isProtected()) {
            throw RoleProtectedException::cannotDelete($role->name()->value);
        }

        $this->roleRepository->delete($role->id());
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    public function assignPermissions(string $roleId, array $permissionKeys): void
    {
        $permissionIds = $this->permissionRepository->findIdsByKeys($permissionKeys);
        $this->roleRepository->attachPermissions(RoleId::fromString($roleId), $permissionIds);
    }

    /**
     * @param  array<string>  $permissionKeys
     */
    public function syncPermissions(string $roleId, array $permissionKeys): void
    {
        $permissionIds = $this->permissionRepository->findIdsByKeys($permissionKeys);
        $this->roleRepository->syncPermissions(RoleId::fromString($roleId), $permissionIds);
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
