<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Domain\Events\RoleDeleted;
use App\Domain\Events\UserActiveRolesChanged;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Announces to the modules what a role deletion or a membership change already does: it
 * changes no roles, no cache and no history. Everything is dispatched after the outermost
 * transaction commits, so a rollback dispatches nothing.
 */
final readonly class RoleChangeAnnouncer
{
    /**
     * Reads the holders now: call it before the role is deleted (the pivot rows go with it).
     *
     * @param  list<string>  $permissionKeys
     */
    public function captureDeletion(string $roleId, string $roleName, array $permissionKeys): RoleDeleted
    {
        $holderIds = DB::table('user_role')
            ->where('role_id', $roleId)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(strval(...))
            ->all();

        sort($permissionKeys);

        return new RoleDeleted($roleId, $roleName, $permissionKeys, array_values($holderIds));
    }

    public function announceDeletion(RoleDeleted $event): void
    {
        DB::afterCommit(static function () use ($event): void {
            Event::dispatch($event);
        });
    }

    /**
     * The association member role changed from $from to $to (null: no usable role, so nobody
     * was or is a member). Accounts that were members and are not, or the other way round,
     * and hold roles requiring membership, get their roles paused or activated.
     */
    public function announceAssociationRoleSwitch(?string $from, ?string $to): void
    {
        if ($from === $to) {
            return;
        }

        DB::afterCommit(function () use ($from, $to): void {
            $before = $this->holderIds($from);
            $after = $this->holderIds($to);

            foreach ($this->holdersOfRolesRequiringMembership() as $userId => $roleNames) {
                $wasMember = isset($before[$userId]);
                $isMember = isset($after[$userId]);

                if ($wasMember !== $isMember) {
                    $this->dispatch($userId, $roleNames, $isMember);
                }
            }
        });
    }

    /**
     * The role $roleName gained ($requires true) or lost the association member requirement.
     * Its holders who are not members ($associationRole holders are) get it paused or activated.
     */
    public function announceRequirementChange(string $roleName, bool $requires, ?string $associationRole): void
    {
        DB::afterCommit(function () use ($roleName, $requires, $associationRole): void {
            $members = $this->holderIds($associationRole);

            $holders = DB::table('user_role')
                ->join('roles', 'roles.id', '=', 'user_role.role_id')
                ->join('users', 'users.id', '=', 'user_role.user_id')
                ->where('roles.name', $roleName)
                ->whereNull('users.deleted_at')
                ->whereNull('users.anonymized_at')
                ->orderBy('user_role.user_id')
                ->pluck('user_role.user_id');

            foreach ($holders as $userId) {
                $userId = (string) $userId;

                if (! isset($members[$userId])) {
                    $this->dispatch($userId, [$roleName], activated: ! $requires);
                }
            }
        });
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function dispatch(string $userId, array $roleNames, bool $activated): void
    {
        sort($roleNames);

        Event::dispatch(new UserActiveRolesChanged(
            $userId,
            $activated ? $roleNames : [],
            $activated ? [] : $roleNames,
        ));
    }

    /**
     * Ids of the live accounts holding $roleName, as keys.
     *
     * @return array<string, true>
     */
    private function holderIds(?string $roleName): array
    {
        if ($roleName === null) {
            return [];
        }

        $ids = [];

        foreach ($this->liveHolders()->where('roles.name', $roleName)->pluck('user_role.user_id') as $id) {
            $ids[(string) $id] = true;
        }

        return $ids;
    }

    /**
     * Live accounts with the roles requiring membership they hold, streamed by account.
     *
     * @return \Generator<string, list<string>>
     */
    private function holdersOfRolesRequiringMembership(): \Generator
    {
        $currentId = null;
        $names = [];

        $rows = $this->liveHolders()
            ->where('roles.requires_association_member', true)
            ->orderBy('user_role.user_id')
            ->select('user_role.user_id', 'roles.name')
            ->cursor();

        foreach ($rows as $row) {
            $userId = (string) $row->user_id;

            if ($currentId !== null && $userId !== $currentId) {
                yield $currentId => $names;
                $names = [];
            }

            $currentId = $userId;
            $names[] = (string) $row->name;
        }

        if ($currentId !== null) {
            yield $currentId => $names;
        }
    }

    private function liveHolders(): Builder
    {
        return DB::table('user_role')
            ->join('roles', 'roles.id', '=', 'user_role.role_id')
            ->join('users', 'users.id', '=', 'user_role.user_id')
            ->whereNull('users.deleted_at')
            ->whereNull('users.anonymized_at');
    }
}
