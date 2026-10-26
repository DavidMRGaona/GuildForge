<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Accounts that hold a role requiring association membership (so the role is inactive for
 * them) without being members: they lack the association member role. Deactivated
 * (soft-deleted) and anonymized accounts are left out.
 */
final readonly class InactiveRoleHolders
{
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
