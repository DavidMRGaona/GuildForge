<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

use App\Domain\Authorization\Entities\Role;
use App\Domain\Authorization\Exceptions\RoleGrantDeniedException;
use App\Domain\Authorization\Repositories\RoleRepositoryInterface;
use App\Domain\Authorization\ValueObjects\RoleName;
use App\Domain\Enums\UserRole;
use InvalidArgumentException;
use Throwable;

/**
 * The one rule that decides who may give or take a role, act on an account, or edit the
 * permissions of a role: nobody grants more than they hold.
 *
 * A role can be granted (or removed) by an admin, or by someone with users.assign_roles when
 * the role is not "admin" and every permission of the role is among the actor's effective
 * permissions (those of their active roles). Removing a role needs the same right as granting
 * it. A role that does not exist is never grantable.
 *
 * A class of its own rather than methods of a core interface: modules implement those
 * interfaces in their tests, and a new method would break them.
 */
final readonly class RoleGrantPolicy
{
    public const string ASSIGN_ROLES_PERMISSION = 'users.assign_roles';

    public function __construct(
        private AuthorizationServiceInterface $authorization,
        private RoleRepositoryInterface $roles,
        private AssociationMembershipServiceInterface $associationMembership,
        private BaseRoleRule $baseRoleRule,
    ) {}

    public function canGrant(object $actor, string $roleName): bool
    {
        $role = $this->findRole($roleName);

        if (! $role instanceof Role) {
            return false;
        }

        if ($this->isAdmin($actor)) {
            return true;
        }

        if (! $this->authorization->can($actor, self::ASSIGN_ROLES_PERMISSION)) {
            return false;
        }

        if ($roleName === UserRole::Admin->value) {
            return false;
        }

        // Every account holds the base role and never loses it.
        if ($this->baseRoleRule->isBaseRoleName($roleName)) {
            return true;
        }

        $actorPermissions = $this->authorization->getPermissions($actor);

        return array_all(
            $this->roles->getPermissionKeys($role->id()),
            static fn (string $key): bool => in_array($key, $actorPermissions, true),
        );
    }

    /**
     * Whether the actor may turn the target's roles from $fromRoleNames into $toRoleNames:
     * every role added or removed must be grantable. Adding the association member role also
     * wakes up the target's roles that require it, so each of those must be grantable too.
     *
     * @param  array<string>  $fromRoleNames
     * @param  array<string>  $toRoleNames
     */
    public function canChangeRoles(object $actor, object $target, array $fromRoleNames, array $toRoleNames): bool
    {
        return $this->deniedRoleNames($actor, $target, $fromRoleNames, $toRoleNames) === [];
    }

    /**
     * @param  array<string>  $fromRoleNames
     * @param  array<string>  $toRoleNames
     *
     * @throws RoleGrantDeniedException with the visible names of the roles the actor cannot grant
     */
    public function assertCanChangeRoles(object $actor, object $target, array $fromRoleNames, array $toRoleNames): void
    {
        $denied = $this->deniedRoleNames($actor, $target, $fromRoleNames, $toRoleNames);

        if ($denied === []) {
            return;
        }

        throw RoleGrantDeniedException::forRoles(array_map(
            fn (string $name): string => $this->findRole($name)?->displayName() ?? $name,
            $denied,
        ));
    }

    /**
     * Whether the actor may act on the account (edit it, deactivate, restore, anonymize or
     * delete it): an admin always may; anyone else only when every role the account holds,
     * active or not, is grantable by them, leaving out the base role and the association
     * member role. An account with "admin" is therefore managed by admins only.
     */
    public function canManageAccount(object $actor, object $target): bool
    {
        if ($this->isAdmin($actor)) {
            return true;
        }

        $associationRole = $this->associationRoleName();

        foreach ($this->roles->getRoleNamesForUser($this->userId($target)) as $roleName) {
            if ($this->baseRoleRule->isBaseRoleName($roleName) || $roleName === $associationRole) {
                continue;
            }

            if (! $this->canGrant($actor, $roleName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function grantableRoleNames(object $actor): array
    {
        $names = [];

        foreach ($this->roles->findAll() as $role) {
            $name = $role->name()->value;

            if ($this->canGrant($actor, $name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The permissions the actor may tick or untick in the role editor: null means all of them
     * (admin); anyone else only the ones they hold.
     *
     * @return list<string>|null
     */
    public function editablePermissionKeys(object $actor): ?array
    {
        if ($this->isAdmin($actor)) {
            return null;
        }

        return array_values($this->authorization->getPermissions($actor));
    }

    /**
     * @param  array<string>  $fromRoleNames
     * @param  array<string>  $toRoleNames
     * @return list<string>
     */
    private function deniedRoleNames(object $actor, object $target, array $fromRoleNames, array $toRoleNames): array
    {
        $added = array_values(array_unique(array_diff($toRoleNames, $fromRoleNames)));
        $removed = array_values(array_unique(array_diff($fromRoleNames, $toRoleNames)));

        $toCheck = array_merge($added, $removed);

        if ($added !== [] && $this->addsAssociationRole($added)) {
            $toCheck = array_merge($toCheck, $this->rolesRequiringMembership(
                array_merge($this->roles->getRoleNamesForUser($this->userId($target)), $toRoleNames),
            ));
        }

        $denied = [];

        foreach (array_unique($toCheck) as $roleName) {
            // The base role is never taken away, so listing it or not changes nothing.
            if ($this->baseRoleRule->isBaseRoleName($roleName)) {
                continue;
            }

            if (! $this->canGrant($actor, $roleName)) {
                $denied[] = $roleName;
            }
        }

        return $denied;
    }

    /**
     * Fails closed: when the association member role cannot be read, any addition might be it.
     *
     * @param  list<string>  $addedRoleNames
     */
    private function addsAssociationRole(array $addedRoleNames): bool
    {
        try {
            $associationRole = $this->associationMembership->roleName();
        } catch (Throwable) {
            return true;
        }

        return $associationRole !== null && in_array($associationRole, $addedRoleNames, true);
    }

    /**
     * Null when there is no usable association member role or it cannot be read: then no role
     * is left out of the account check, which only makes it stricter.
     */
    private function associationRoleName(): ?string
    {
        try {
            return $this->associationMembership->roleName();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A role that cannot be read is kept too (fails closed): it is checked, and refused.
     *
     * @param  array<string>  $roleNames
     * @return list<string>
     */
    private function rolesRequiringMembership(array $roleNames): array
    {
        return array_values(array_filter(
            array_unique($roleNames),
            fn (string $name): bool => $this->findRole($name)?->requiresAssociationMember() ?? true,
        ));
    }

    /**
     * Null for a role that does not exist or whose name is not a valid role name.
     */
    private function findRole(string $roleName): ?Role
    {
        try {
            return $this->roles->findByName(new RoleName($roleName));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function isAdmin(object $actor): bool
    {
        return $this->authorization->hasRole($actor, UserRole::Admin->value);
    }

    /**
     * The user's key, read the way AuthorizationService reads it: the id property, else getKey().
     */
    private function userId(object $user): string
    {
        $id = $user->id ?? (method_exists($user, 'getKey') ? $user->getKey() : null);

        if (! is_int($id) && ! is_string($id)) {
            throw new InvalidArgumentException('The user has no readable id.');
        }

        return (string) $id;
    }
}
