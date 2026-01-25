<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

use App\Application\Authorization\DTOs\CreateRoleDTO;
use App\Application\Authorization\DTOs\RoleResponseDTO;
use App\Application\Authorization\DTOs\UpdateRoleDTO;

interface RoleServiceInterface
{
    /**
     * Get all roles.
     *
     * @return array<RoleResponseDTO>
     */
    public function all(): array;

    /**
     * Find a role by ID.
     */
    public function findById(string $id): ?RoleResponseDTO;

    /**
     * Find a role by name.
     */
    public function findByName(string $name): ?RoleResponseDTO;

    /**
     * Create a new role.
     */
    public function create(CreateRoleDTO $dto): RoleResponseDTO;

    /**
     * Update an existing role.
     */
    public function update(string $id, UpdateRoleDTO $dto): RoleResponseDTO;

    /**
     * Delete a role by ID.
     *
     * @throws \App\Domain\Authorization\Exceptions\RoleProtectedException
     */
    public function delete(string $id): void;

    /**
     * Assign permissions to a role.
     *
     * @param  array<string>  $permissionKeys
     */
    public function assignPermissions(string $roleId, array $permissionKeys): void;

    /**
     * Sync permissions for a role (replace all).
     *
     * @param  array<string>  $permissionKeys
     */
    public function syncPermissions(string $roleId, array $permissionKeys): void;
}
