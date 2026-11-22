<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\AssociationMembersAmongInterface;
use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Domain\Authorization\Exceptions\AssociationMembersUnavailableException;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The roles of all the accounts at once, each account's passed through ActiveRoleResolver::filter() (the
 * rule AuthorizationService caches per account), and a member is whoever keeps the association role after
 * it. The two-factor reader is asked once for all of them, and only when a role needs it; TwoFactorHolders
 * swallows a failing reader (so roles stay paused), and here that is an error, not an answer.
 *
 * TwoFactorHolders is bound scoped: resolved on each call, so a long-lived instance of this one is safe.
 */
final readonly class AssociationMembersAmong implements AssociationMembersAmongInterface
{
    public function __construct(
        private AssociationMembershipServiceInterface $membership,
        private ActiveRoleResolver $resolver,
    ) {}

    public function among(array $userIds): array
    {
        $ids = [];
        foreach ($userIds as $userId) {
            if (Str::isUuid($userId)) {
                $ids[strtolower($userId)] = true;
            }
        }

        $ids = array_keys($ids);

        if ($ids === []) {
            return [];
        }

        $roleName = $this->membership->roleName();

        if ($roleName === null) {
            return [];
        }

        /** @var array<string, Collection<int, RoleModel>> $rolesOf */
        $rolesOf = [];
        $rows = RoleModel::query()
            ->join('user_role', 'user_role.role_id', '=', 'roles.id')
            ->whereIn('user_role.user_id', $ids)
            ->get([...ActiveRoleResolver::ROLE_COLUMNS, 'user_role.user_id as holder_id']);

        foreach ($rows as $role) {
            $holder = strtolower((string) $role->getAttribute('holder_id'));
            $rolesOf[$holder] ??= new Collection;
            $rolesOf[$holder]->push($role);
        }

        $twoFactor = app(TwoFactorHolders::class);
        $withTwoFactor = null;
        $members = [];

        foreach ($rolesOf as $holder => $roles) {
            $active = $this->resolver->filter(
                $roles,
                static fn (): string => $roleName,
                static function () use (&$withTwoFactor, $twoFactor, $rolesOf, $holder): bool {
                    if ($withTwoFactor === null) {
                        $failuresBefore = $twoFactor->failures();
                        $withTwoFactor = $twoFactor->among(array_map(strval(...), array_keys($rolesOf)));

                        if ($twoFactor->failures() > $failuresBefore) {
                            throw AssociationMembersUnavailableException::twoFactorUnreadable();
                        }
                    }

                    return isset($withTwoFactor[$holder]);
                },
            );

            if ($active->contains(static fn (RoleModel $role): bool => $role->name === $roleName)) {
                $members[$holder] = true;
            }
        }

        return $members;
    }
}
