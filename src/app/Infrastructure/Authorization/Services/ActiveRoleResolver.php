<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\AdminOnlyPermissionRule;
use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Which of the roles an account holds count for it, and what they give. AuthorizationService
 * asks this (and caches the answer); the panel asks it too, to tell someone whose access is
 * paused only because two-factor authentication is off (core#772).
 *
 * A role that requires association membership counts only while the account holds the
 * association member role (read from its own roles, unfiltered). A role that requires
 * two-factor authentication counts only while the account has it on. Membership is decided
 * first and from the stored roles, so a pause for two-factor never pauses others in cascade.
 * Every check that fails leaves the roles it guards paused.
 */
final readonly class ActiveRoleResolver
{
    /**
     * @param  bool  $assumeTwoFactor  answer as if the account had two-factor authentication on
     * @return Collection<int, RoleModel>
     */
    public function activeRoles(UserModel $user, bool $assumeTwoFactor = false): Collection
    {
        $roles = $user->roles()->get(['roles.id', 'roles.name', 'roles.requires_association_member', 'roles.requires_two_factor']);
        $requiresMembership = static fn (RoleModel $role): bool => $role->requires_association_member === true;
        $requiresTwoFactor = static fn (RoleModel $role): bool => $role->requires_two_factor === true;

        if ($roles->contains($requiresMembership)) {
            $associationRole = $this->associationMemberRoleName();
            $isAssociationMember = $associationRole !== null
                && $roles->contains(fn (RoleModel $role): bool => $role->name === $associationRole);

            if (! $isAssociationMember) {
                $roles = $roles->reject($requiresMembership)->values();
            }
        }

        if (! $assumeTwoFactor
            && $roles->contains($requiresTwoFactor)
            && ! app(TwoFactorHolders::class)->has((string) $user->getKey())) {
            return $roles->reject($requiresTwoFactor)->values();
        }

        return $roles;
    }

    /**
     * The permission keys of the given roles. An admin-only permission written into another role
     * gives nothing (fails closed).
     *
     * @param  Collection<int, RoleModel>  $roles
     * @return list<string>
     */
    public function permissionKeys(Collection $roles): array
    {
        $ids = $roles->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $adminOnly = app(AdminOnlyPermissionRule::class);

        /** @var list<string> */
        return RoleModel::query()
            ->whereIn('id', $ids)
            ->with('permissions')
            ->get()
            ->flatMap(fn (RoleModel $role) => $role->permissions->pluck('key')
                ->filter(static fn (mixed $key): bool => is_string($key) && $adminOnly->mayHold($role->name, $key)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The roles that are paused only because the account has two-factor authentication off:
     * they would count with it on. Names, sorted.
     *
     * @return list<string>
     */
    public function pausedByTwoFactor(UserModel $user): array
    {
        $withTwoFactor = $this->activeRoles($user, assumeTwoFactor: true);

        if (! $withTwoFactor->contains(static fn (RoleModel $role): bool => $role->requires_two_factor === true)) {
            return [];
        }

        /** @var list<string> $names */
        $names = $withTwoFactor->pluck('name')
            ->diff($this->activeRoles($user)->pluck('name'))
            ->map(strval(...))
            ->sort()
            ->values()
            ->all();

        return $names;
    }

    /**
     * The membership service is resolved here, not injected: it depends on authorization.
     * roleName() reads only the setting. Unreadable: no association member role, so the roles
     * that require it stay inactive.
     */
    public function associationMemberRoleName(): ?string
    {
        try {
            return app(AssociationMembershipServiceInterface::class)->roleName();
        } catch (Throwable $e) {
            Log::warning("Association member role setting unreadable, roles that require it stay inactive: {$e->getMessage()}");

            return null;
        }
    }
}
