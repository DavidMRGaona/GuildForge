<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Repositories;

use App\Domain\Authorization\Entities\Role;
use App\Domain\Authorization\ValueObjects\RoleId;
use App\Domain\Authorization\ValueObjects\RoleName;

interface RoleRepositoryInterface
{
    public function findById(RoleId $id): ?Role;

    public function findByName(RoleName $name): ?Role;

    /**
     * @return array<Role>
     */
    public function findAll(): array;

    /**
     * The stored name of every role, as stored: unlike findAll(), a name the domain no longer
     * accepts (RoleName) is listed too instead of failing the whole listing.
     *
     * @return list<string>
     */
    public function allNames(): array;

    public function save(Role $role): void;

    public function delete(RoleId $id): void;

    /**
     * Sync permissions for a role (replaces all existing).
     *
     * @param  array<string>  $permissionIds
     */
    public function syncPermissions(RoleId $roleId, array $permissionIds): void;

    /**
     * Attach permissions to a role (without removing existing).
     *
     * @param  array<string>  $permissionIds
     */
    public function attachPermissions(RoleId $roleId, array $permissionIds): void;

    /**
     * Get permission keys for a role.
     *
     * @return array<string>
     */
    public function getPermissionKeys(RoleId $roleId): array;

    /**
     * Get the names of every role a user holds, read from the database (no cache, no filtering).
     *
     * @return array<string>
     */
    public function getRoleNamesForUser(string $userId): array;
}
