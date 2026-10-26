<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

use App\Domain\Enums\UserRole;

/**
 * The one place that decides whether a role may carry a restriction (the association member
 * requirement or a cap on its holders). The administrator role, the base role of every account
 * and the association member role never can: they are what the restrictions are measured
 * against, and a cap or requirement on them would lock accounts out.
 *
 * A class of its own rather than a method of a core interface: modules implement those
 * interfaces in their tests, and a new method would break them.
 */
final readonly class RoleRestrictionRule
{
    public function __construct(
        private BaseRoleRule $baseRole,
        private AssociationMemberRoleRule $associationMemberRole,
    ) {}

    public function canBeRestricted(string $roleName): bool
    {
        return $roleName !== UserRole::Admin->value
            && ! $this->baseRole->isBaseRoleName($roleName)
            && ! $this->associationMemberRole->isAssociationMemberRoleName($roleName);
    }
}
