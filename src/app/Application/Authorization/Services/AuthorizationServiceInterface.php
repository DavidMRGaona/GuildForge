<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

interface AuthorizationServiceInterface
{
    /**
     * Check if a user has a specific permission.
     * Receives object $user for fast-path (isAdmin without query).
     */
    public function can(object $user, string $permissionKey): bool;

    /**
     * Check if a user has any of the given permissions.
     *
     * @param  array<string>  $permissionKeys
     */
    public function canAny(object $user, array $permissionKeys): bool;

    /**
     * Check if a user has all of the given permissions.
     *
     * @param  array<string>  $permissionKeys
     */
    public function canAll(object $user, array $permissionKeys): bool;

    /**
     * Check if a user has a specific role.
     */
    public function hasRole(object $user, string $roleName): bool;

    /**
     * Check if a user has any of the given roles.
     *
     * @param  array<string>  $roleNames
     */
    public function hasAnyRole(object $user, array $roleNames): bool;

    /**
     * Get all permission keys for a user.
     *
     * @return array<string>
     */
    public function getPermissions(object $user): array;

    /**
     * Get all role names for a user.
     *
     * @return array<string>
     */
    public function getRoles(object $user): array;

    /**
     * Assign a role to a user.
     */
    public function assignRole(object $user, string $roleName): void;

    /**
     * Remove a role from a user.
     */
    public function removeRole(object $user, string $roleName): void;

    /**
     * Sync roles for a user (replace all).
     *
     * @param  array<string>  $roleNames
     */
    public function syncRoles(object $user, array $roleNames): void;
}
