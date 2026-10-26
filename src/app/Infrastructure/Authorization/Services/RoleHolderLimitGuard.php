<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\BaseRoleRule;
use App\Domain\Authorization\Exceptions\RoleHolderLimitReachedException;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The holder limit of a role (max_holders, null = no limit). Holders are the accounts that
 * have the role in the pivot, whether it is active for them or not (a role that requires
 * association membership still counts for a holder who is not a member), except deactivated
 * (soft-deleted) and anonymized accounts. Lowering a limit removes nobody: it only stops new
 * holders.
 */
final readonly class RoleHolderLimitGuard
{
    /**
     * Throws when $role is full for $user, who is not counted. Locks the role row: run it
     * inside the transaction that writes the assignment, so two assignments at the same time
     * cannot both take the last place.
     *
     * @throws RoleHolderLimitReachedException
     */
    public function assertCanHold(UserModel $user, RoleModel $role): void
    {
        $locked = $this->lockRole($role);

        if (! $locked instanceof RoleModel || $locked->max_holders === null) {
            return;
        }

        $holders = $this->otherHolders($user, $locked);

        if ($holders->count() >= $locked->max_holders) {
            throw RoleHolderLimitReachedException::forRole(
                $this->roleDisplayName($locked),
                array_values($holders->map(static fn (UserModel $holder): string => $holder->display_name ?? $holder->name)->all()),
            );
        }
    }

    /**
     * For an account that comes back (restored): takes away the roles that filled up while it
     * was away, through the authorization service so its cached roles are dropped. The base
     * role of every account is never taken away.
     *
     * @return list<string> the visible names of the roles taken away
     */
    public function dropRolesOverLimit(UserModel $user): array
    {
        return DB::transaction(function () use ($user): array {
            $capped = $user->roles()
                ->whereNotNull('roles.max_holders')
                // The base role takes no limit; even if one were stored, it is never taken away
                ->where('roles.name', '!=', BaseRoleRule::NAME)
                ->orderBy('roles.name')
                ->get(['roles.id', 'roles.name', 'roles.display_name', 'roles.max_holders']);

            $dropped = [];

            foreach ($capped as $role) {
                $locked = $this->lockRole($role);

                if (! $locked instanceof RoleModel || $locked->max_holders === null) {
                    continue;
                }

                if ($this->otherHolders($user, $locked)->count() >= $locked->max_holders) {
                    // Resolved here, not injected: the authorization service depends on this guard
                    app(AuthorizationServiceInterface::class)->removeRole($user, $locked->name);
                    $dropped[] = $this->roleDisplayName($locked);
                }
            }

            return $dropped;
        });
    }

    private function lockRole(RoleModel $role): ?RoleModel
    {
        return RoleModel::query()
            ->whereKey($role->getKey())
            ->lockForUpdate()
            ->first(['id', 'name', 'display_name', 'max_holders']);
    }

    /**
     * Read from the pivot, not from the active roles: an inactive role still takes a place.
     *
     * @return Collection<int, UserModel>
     */
    private function otherHolders(UserModel $user, RoleModel $role): Collection
    {
        return UserModel::query()
            // Explicit about who counts: deactivated and anonymized accounts never do
            ->withTrashed()
            ->whereNull('users.deleted_at')
            ->whereNull('users.anonymized_at')
            ->whereKeyNot($user->getKey())
            ->whereHas('roles', static function (Builder $query) use ($role): void {
                $query->whereKey($role->getKey());
            })
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.display_name']);
    }

    private function roleDisplayName(RoleModel $role): string
    {
        return $role->display_name !== '' ? $role->display_name : $role->name;
    }
}
