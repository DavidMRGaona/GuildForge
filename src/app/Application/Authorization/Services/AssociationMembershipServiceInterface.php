<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

use App\Domain\Enums\UserRole;

/**
 * Single source of truth for "is this user a member of the association?".
 *
 * "member" is the role of any registered user; the association member is the role chosen
 * in the site settings ("socio" by default). Modules must ask this service instead of
 * checking a role name themselves.
 */
interface AssociationMembershipServiceInterface
{
    /** Settings key holding the association member role name. */
    public const string SETTING_KEY = 'association_member_role';

    /** Role used when nothing is configured. */
    public const string DEFAULT_ROLE = 'socio';

    /** Role names that can never be the association member role. */
    public const array FORBIDDEN_ROLES = [
        UserRole::Admin->value,
        UserRole::Editor->value,
        UserRole::Member->value,
    ];

    /** Configured role name (DEFAULT_ROLE when unset or blank), or null when the stored value is forbidden. */
    public function roleName(): ?string;

    /** Whether the user holds the configured role; false when roleName() is null or the role does not exist. */
    public function isAssociationMember(object $user): bool;
}
