<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

/**
 * The one place that decides whether a role is the association member role set in the site
 * settings. Modules find association members by that role's name, so the role panel, the
 * role policy and the role service use this to keep it from being renamed or deleted.
 *
 * A class of its own rather than a method of AssociationMembershipServiceInterface: modules
 * implement that interface in their tests, and a new method would break them.
 */
final readonly class AssociationMemberRoleRule
{
    public function __construct(
        private AssociationMembershipServiceInterface $associationMembership,
    ) {}

    /**
     * Exact comparison with the configured role name; false for every role while the setting
     * holds a role that cannot be used (roleName() is null then).
     */
    public function isAssociationMemberRoleName(string $name): bool
    {
        $roleName = $this->associationMembership->roleName();

        return $roleName !== null && $name === $roleName;
    }
}
