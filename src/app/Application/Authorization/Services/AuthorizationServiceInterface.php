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
     *
     * The base role of every account ("member", see BaseRoleRule) is never removed: asking for
     * it does nothing, without an exception. Anonymizing an account is the only way to strip
     * it, and that detaches the roles directly.
     */
    public function removeRole(object $user, string $roleName): void;

    /**
     * Sync roles for a user (replace all).
     *
     * A user who has the base role of every account ("member", see BaseRoleRule) keeps it even
     * when it is not in $roleNames; a user without it does not get it from here.
     *
     * @param  array<string>  $roleNames
     */
    public function syncRoles(object $user, array $roleNames): void;

    /**
     * Invalidate every cached role and permission lookup, so the next checks read them again.
     *
     * Call it after any change to a user's roles or to a role (its permissions, its name,
     * its deletion) made without assignRole(), removeRole() or syncRoles(). Inside a
     * database transaction it takes effect once the transaction commits.
     */
    public function invalidateCache(): void;
}
