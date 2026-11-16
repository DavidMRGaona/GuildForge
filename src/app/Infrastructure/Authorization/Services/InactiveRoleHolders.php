<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Accounts that hold a role requiring association membership (so the role is inactive for
 * them) without being members: they lack the association member role. Also (core#772) the
 * accounts that hold a role requiring two-factor authentication with it off, asked through
 * TwoFactorHolders. Deactivated (soft-deleted) and anonymized accounts are left out.
 *
 * Each reason is counted on its own: a role waiting for membership is listed under membership,
 * and under two-factor only once membership would no longer hold it back.
 */
final class InactiveRoleHolders
{
    /** @var array<string, list<string>> withoutTwoFactorIds() per role filter, for the request (bound scoped) */
    private array $withoutTwoFactor = [];

    /**
     * @param  string|null  $associationRoleName  the association member role; null means nobody is a member
     * @return Builder<UserModel>
     */
    public function query(?string $associationRoleName): Builder
    {
        $query = UserModel::query()
            ->whereNull('anonymized_at')
            ->whereHas('roles', static function (Builder $roles): void {
                $roles->where('requires_association_member', true);
            });

        if ($associationRoleName !== null) {
            $query->whereDoesntHave('roles', static function (Builder $roles) use ($associationRoleName): void {
                $roles->where('name', $associationRoleName);
            });
        }

        return $query;
    }

    /**
     * Ids of the accounts that hold a role requiring two-factor authentication (one of
     * $roleNames, when given), have it off, and would have that role in force with it on (so
     * not one only waiting for membership), as stored. Read once per request and filter.
     *
     * @param  list<string>|null  $roleNames
     * @return list<string>
     */
    public function withoutTwoFactorIds(?array $roleNames = null): array
    {
        $key = $roleNames === null ? '*' : implode("\0", array_unique($roleNames));

        return $this->withoutTwoFactor[$key] ??= $this->readWithoutTwoFactor($roleNames);
    }

    /**
     * The accounts of withoutTwoFactorIds() as a query.
     *
     * @param  list<string>|null  $roleNames
     * @return Builder<UserModel>
     */
    public function withoutTwoFactorQuery(?array $roleNames = null): Builder
    {
        return UserModel::query()->whereIn('users.id', $this->withoutTwoFactorIds($roleNames));
    }

    /**
     * @param  list<string>|null  $roleNames
     * @return list<string>
     */
    private function readWithoutTwoFactor(?array $roleNames): array
    {
        $holders = UserModel::query()
            ->whereNull('anonymized_at')
            ->whereHas('roles', static function (Builder $roles) use ($roleNames): void {
                $roles->where('requires_two_factor', true)
                    ->when($roleNames !== null, static fn (Builder $query): Builder => $query->whereIn('name', $roleNames ?? []));
            })
            ->with('roles')
            ->orderBy('id')
            ->get();

        /** @var list<string> $ids */
        $ids = $holders->map(static fn (UserModel $holder): string => (string) $holder->getKey())->all();
        $on = app(TwoFactorHolders::class)->among($ids);
        $resolver = app(ActiveRoleResolver::class);
        $associationRole = $ids === [] ? null : $resolver->associationMemberRoleName();

        $paused = [];

        foreach ($holders as $holder) {
            $id = (string) $holder->getKey();

            if (isset($on[strtolower($id)])) {
                continue;
            }

            $inForceWithTwoFactor = $resolver->filter(
                $holder->roles,
                static fn (): ?string => $associationRole,
                static fn (): bool => true,
            );

            if ($inForceWithTwoFactor->contains(static fn (RoleModel $role): bool => $role->requires_two_factor
                && ($roleNames === null || in_array($role->name, $roleNames, true)))) {
                $paused[] = $id;
            }
        }

        return $paused;
    }

    public function count(?string $associationRoleName): int
    {
        return $this->query($associationRoleName)->count();
    }

    /**
     * The roles requiring association membership that holders of $roleName hold (read from
     * the pivot, so inactive ones too): making $roleName the association member role would
     * turn them on. Deactivated and anonymized holders are left out.
     *
     * @return list<string> role names, sorted
     */
    public function rolesRequiringMembershipHeldByHoldersOf(string $roleName): array
    {
        return $this->rolesRequiringMembershipHeldBy($roleName, null);
    }

    /**
     * The roles requiring association membership that would pause if the association member
     * role went from $fromRoleName to $toRoleName: those held by holders of $fromRoleName who
     * do not hold $toRoleName, as they stop being members. Read from the pivot and leaving out
     * deactivated and anonymized holders, like rolesRequiringMembershipHeldByHoldersOf().
     *
     * @return list<string> role names, sorted
     */
    public function rolesRequiringMembershipPausedBySwitching(string $fromRoleName, string $toRoleName): array
    {
        return $this->rolesRequiringMembershipHeldBy($fromRoleName, $toRoleName);
    }

    /**
     * @param  string|null  $exceptHoldersOf  leave out the holders of this role
     * @return list<string>
     */
    private function rolesRequiringMembershipHeldBy(string $roleName, ?string $exceptHoldersOf): array
    {
        $query = DB::table('user_role as candidate')
            ->join('roles as candidate_role', 'candidate_role.id', '=', 'candidate.role_id')
            ->join('users', 'users.id', '=', 'candidate.user_id')
            ->join('user_role as held', 'held.user_id', '=', 'candidate.user_id')
            ->join('roles', 'roles.id', '=', 'held.role_id')
            ->where('candidate_role.name', $roleName)
            ->where('roles.requires_association_member', true)
            ->whereNull('users.deleted_at')
            ->whereNull('users.anonymized_at');

        if ($exceptHoldersOf !== null) {
            $query->whereNotExists(static function (QueryBuilder $kept) use ($exceptHoldersOf): void {
                $kept->selectRaw('1')
                    ->from('user_role as kept')
                    ->join('roles as kept_role', 'kept_role.id', '=', 'kept.role_id')
                    ->whereColumn('kept.user_id', 'candidate.user_id')
                    ->where('kept_role.name', $exceptHoldersOf);
            });
        }

        return array_values($query
            ->distinct()
            ->orderBy('roles.name')
            ->pluck('roles.name')
            ->map(strval(...))
            ->all());
    }
}
