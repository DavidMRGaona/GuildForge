<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Infrastructure\Authorization\Services\ActiveRoleResolver;
use App\Infrastructure\Authorization\Services\TwoFactorHolders;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/**
 * How many people a role would leave inactive if it started requiring association membership,
 * or two-factor authentication (core#772).
 */
final readonly class RoleRequirementImpact
{
    public function __construct(private AssociationMembershipServiceInterface $membership) {}

    /**
     * Holders of the role (not deactivated, not anonymized) who do not hold the association
     * member role. When no association role is configured, every holder counts.
     */
    public function inactiveHolderCount(RoleModel $role): int
    {
        $associationRole = $this->membership->roleName();

        return $role->users()
            ->whereNull('users.anonymized_at')
            ->when(
                $associationRole !== null,
                static fn ($query) => $query->whereDoesntHave(
                    'roles',
                    static fn ($roles) => $roles->where('roles.name', $associationRole),
                ),
            )
            ->count();
    }

    /**
     * Holders of the role (not deactivated, not anonymized) with two-factor authentication off,
     * asked through TwoFactorHolders, for whom the requirement would pause it. A holder for whom
     * the role already waits for membership is left out: they are counted under that reason.
     */
    public function holdersWithoutTwoFactorCount(RoleModel $role): int
    {
        $holders = $role->users()->whereNull('users.anonymized_at')->with('roles')->get();

        if ($holders->isEmpty()) {
            return 0;
        }

        /** @var list<string> $ids */
        $ids = $holders->map(static fn (UserModel $holder): string => (string) $holder->getKey())->all();
        $on = app(TwoFactorHolders::class)->among($ids);
        $resolver = app(ActiveRoleResolver::class);
        $associationRole = $this->membership->roleName();

        return $holders->filter(static fn (UserModel $holder): bool => ! isset($on[strtolower((string) $holder->getKey())])
            && $resolver->filter($holder->roles, static fn (): ?string => $associationRole, static fn (): bool => true)
                ->contains('name', $role->name))
            ->count();
    }
}
