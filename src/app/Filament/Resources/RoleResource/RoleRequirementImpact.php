<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleResource;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;

/**
 * How many people a role would leave inactive if it started requiring association membership.
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
}
