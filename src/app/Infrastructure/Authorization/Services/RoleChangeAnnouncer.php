<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Domain\Events\RoleDeleted;
use App\Domain\Events\TwoFactorRolesPaused;
use App\Domain\Events\UserActiveRolesChanged;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Announces to the modules what a role deletion, a membership change or a change of the
 * two-factor requirement already does: it changes no roles, no cache and no history. Everything is dispatched after the outermost
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
     * and hold roles requiring membership, get their roles paused or activated. A role that
     * also requires two-factor authentication (core#772) changes only for an account that has
     * it on; one that has it off is told the role now waits for it, once it became a member.
     */
    public function announceAssociationRoleSwitch(?string $from, ?string $to): void
    {
        if ($from === $to) {
            return;
        }

        DB::afterCommit(function () use ($from, $to): void {
            $before = $this->holderIds($from);
            $after = $this->holderIds($to);
            $holders = iterator_to_array($this->holdersOfRolesRequiringMembership());
            $withTwoFactor = app(TwoFactorHolders::class)->among(array_map(strval(...), array_keys($holders)));

            foreach ($holders as $userId => $roles) {
                $userId = (string) $userId;
                $wasMember = isset($before[$userId]);
                $isMember = isset($after[$userId]);

                if ($wasMember === $isMember) {
                    continue;
                }

                [$changing, $waitingForTwoFactor] = $this->splitByTwoFactor($roles, isset($withTwoFactor[strtolower($userId)]));

                if ($changing !== []) {
                    $this->dispatch($userId, $changing, $isMember);
                }

                if ($isMember && $waitingForTwoFactor !== []) {
                    $this->announceTwoFactorWait($userId, $waitingForTwoFactor);
                }
            }
        });
    }

    /**
     * The role $roleName gained ($requires true) or lost the association member requirement.
     * Its holders who are not members ($associationRole holders are) get it paused or activated;
     * when the role also requires two-factor authentication (core#772), only those who have it
     * on, while those who have it off are told it now waits for it, once membership no longer
     * holds it back.
     */
    public function announceRequirementChange(string $roleName, bool $requires, ?string $associationRole): void
    {
        DB::afterCommit(function () use ($roleName, $requires, $associationRole): void {
            $members = $this->holderIds($associationRole);
            $requiresTwoFactor = DB::table('roles')->where('name', $roleName)->value('requires_two_factor');

            /** @var list<string> $nonMembers */
            $nonMembers = DB::table('user_role')
                ->join('roles', 'roles.id', '=', 'user_role.role_id')
                ->join('users', 'users.id', '=', 'user_role.user_id')
                ->where('roles.name', $roleName)
                ->whereNull('users.deleted_at')
                ->whereNull('users.anonymized_at')
                ->orderBy('user_role.user_id')
                ->pluck('user_role.user_id')
                ->map(strval(...))
                ->reject(static fn (string $userId): bool => isset($members[$userId]))
                ->values()
                ->all();

            $withTwoFactor = (bool) $requiresTwoFactor ? app(TwoFactorHolders::class)->among($nonMembers) : [];

            foreach ($nonMembers as $userId) {
                if (! (bool) $requiresTwoFactor || isset($withTwoFactor[strtolower($userId)])) {
                    $this->dispatch($userId, [$roleName], activated: ! $requires);
                } elseif (! $requires) {
                    $this->announceTwoFactorWait($userId, [$roleName]);
                }
            }
        });
    }

    /**
     * The role $roleName started ($requires true) or stopped requiring two-factor authentication
     * (core#772). Its holders who have it off get the role paused (and are told) or activated;
     * a holder for whom the role waits for membership anyway sees no change. Read in batches:
     * the holders with their roles, their two-factor state and the association role once.
     */
    public function announceTwoFactorRequirementChange(string $roleName, bool $requires): void
    {
        DB::afterCommit(function () use ($roleName, $requires): void {
            $resolver = app(ActiveRoleResolver::class);
            $associationRole = $resolver->associationMemberRoleName();

            UserModel::query()
                ->whereNull('anonymized_at')
                ->whereHas('roles', static fn ($roles) => $roles->where('roles.name', $roleName))
                ->with('roles')
                ->orderBy('id')
                ->chunk(200, function ($holders) use ($resolver, $associationRole, $roleName, $requires): void {
                    /** @var list<string> $ids */
                    $ids = $holders->map(static fn (UserModel $holder): string => (string) $holder->getKey())->all();
                    $withTwoFactor = app(TwoFactorHolders::class)->among($ids);

                    foreach ($holders as $holder) {
                        $userId = (string) $holder->getKey();

                        if (isset($withTwoFactor[strtolower($userId)])) {
                            continue;
                        }

                        // Only where membership does not hold it back anyway
                        $counts = $resolver->filter($holder->roles, static fn (): ?string => $associationRole, static fn (): bool => true)
                            ->contains('name', $roleName);

                        if (! $counts) {
                            continue;
                        }

                        $this->dispatch($userId, [$roleName], activated: ! $requires);

                        if ($requires) {
                            $this->announceTwoFactorWait($userId, [$roleName]);
                        }
                    }
                });
        });
    }

    /**
     * Splits roles requiring membership into those that change with membership and those that
     * also wait for two-factor authentication the account has off (core#772).
     *
     * @param  list<array{name: string, requires_two_factor: bool}>  $roles
     * @return array{0: list<string>, 1: list<string>}
     */
    private function splitByTwoFactor(array $roles, bool $hasTwoFactor): array
    {
        $changing = [];
        $waiting = [];

        foreach ($roles as $role) {
            if ($role['requires_two_factor'] && ! $hasTwoFactor) {
                $waiting[] = $role['name'];
            } else {
                $changing[] = $role['name'];
            }
        }

        return [$changing, $waiting];
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function announceTwoFactorWait(string $userId, array $roleNames): void
    {
        sort($roleNames);

        Event::dispatch(new TwoFactorRolesPaused($userId, $roleNames));
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
     * Live accounts with the roles requiring membership they hold (name and whether the role
     * also requires two-factor authentication), streamed by account.
     *
     * @return \Generator<string, list<array{name: string, requires_two_factor: bool}>>
     */
    private function holdersOfRolesRequiringMembership(): \Generator
    {
        $currentId = null;
        $roles = [];

        $rows = $this->liveHolders()
            ->where('roles.requires_association_member', true)
            ->orderBy('user_role.user_id')
            ->select('user_role.user_id', 'roles.name', 'roles.requires_two_factor')
            ->cursor();

        foreach ($rows as $row) {
            $userId = (string) $row->user_id;

            if ($currentId !== null && $userId !== $currentId) {
                yield $currentId => $roles;
                $roles = [];
            }

            $currentId = $userId;
            $roles[] = ['name' => (string) $row->name, 'requires_two_factor' => (bool) $row->requires_two_factor];
        }

        if ($currentId !== null) {
            yield $currentId => $roles;
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
