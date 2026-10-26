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

        if ($this->isAdmin($actor)) {
            // Any role that exists, even one whose stored name the domain no longer accepts
            return $role instanceof Role || in_array($roleName, $this->roles->allNames(), true);
        }

        // Fails closed: a role that does not exist or cannot be read is never grantable
        if (! $role instanceof Role) {
            return false;
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
     * Whether the actor may turn the target's roles into $toRoleNames: every role added or
     * removed must be grantable. Adding the association member role also wakes up the target's
     * roles that require it, so each of those must be grantable too.
     *
     * The starting roles are the ones the target holds in the database whenever it has an id:
     * $fromRoleNames is then ignored, so a forged or stale "before" state cannot hide a
     * removal. Only for an account not saved yet (no id) is $fromRoleNames the starting point,
     * and it is taken to hold no roles in the database.
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
     * Whether the actor may act on the account (edit it, change its email or password,
     * deactivate, restore, anonymize or delete it). Whoever controls an account gets what its
     * roles give, so: an admin always may; anyone else only when the account does not hold
     * "admin" and every permission of every role it holds is among the actor's effective
     * permissions. Every role counts: inactive ones, the base role and the association member
     * role included. A role that cannot be read makes the account unmanageable (fails closed).
     *
     * It does not need users.assign_roles: that is for granting roles, while acting on an
     * account is gated by its own permissions (UserPolicy).
     */
    public function canManageAccount(object $actor, object $target): bool
    {
        if ($this->isAdmin($actor)) {
            return true;
        }

        $roleNames = $this->persistedRoleNames($target) ?? [];

        if (in_array(UserRole::Admin->value, $roleNames, true)) {
            return false;
        }

        $actorPermissions = $this->authorization->getPermissions($actor);

        foreach ($roleNames as $roleName) {
            $role = $this->findRole($roleName);

            if (! $role instanceof Role) {
                return false;
            }

            foreach ($this->roles->getPermissionKeys($role->id()) as $key) {
                if (! in_array($key, $actorPermissions, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Never fails on a stored name the domain no longer accepts: an admin gets it like any
     * other role, anyone else never does.
     *
     * @return list<string>
     */
    public function grantableRoleNames(object $actor): array
    {
        $names = $this->roles->allNames();

        if ($this->isAdmin($actor)) {
            return $names;
        }

        return array_values(array_filter(
            $names,
            fn (string $name): bool => $this->canGrant($actor, $name),
        ));
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
        $persisted = $this->persistedRoleNames($target);
        $from = $persisted ?? $fromRoleNames;

        $added = array_values(array_unique(array_diff($toRoleNames, $from)));
        $removed = array_values(array_unique(array_diff($from, $toRoleNames)));

        $toCheck = array_merge($added, $removed);

        if ($added !== [] && $this->addsAssociationRole($added)) {
            $toCheck = array_merge($toCheck, $this->rolesRequiringMembership(
                array_merge($persisted ?? [], $toRoleNames),
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
     * Every role the user holds in the database, inactive ones included; null for an account
     * not saved yet (no id).
     *
     * @return array<string>|null
     */
    private function persistedRoleNames(object $user): ?array
    {
        $id = $this->userId($user);

        return $id === null ? null : $this->roles->getRoleNamesForUser($id);
    }

    /**
     * The user's key, read the way AuthorizationService reads it: the id property, else getKey().
     * Null when there is none yet.
     */
    private function userId(object $user): ?string
    {
        $id = $user->id ?? (method_exists($user, 'getKey') ? $user->getKey() : null);

        if ($id === null) {
            return null;
        }

        if (! is_int($id) && ! is_string($id)) {
            throw new InvalidArgumentException('The user has no readable id.');
        }

        return (string) $id;
    }
}
