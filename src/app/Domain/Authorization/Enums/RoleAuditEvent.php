<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Enums;

/**
 * What an entry of the role history records: a role given to or taken from an account, or a
 * change to a role's definition.
 */
enum RoleAuditEvent: string
{
    case Assigned = 'assigned';
    case Removed = 'removed';
    case RoleCreated = 'role_created';
    case RoleUpdated = 'role_updated';
    case RoleDeleted = 'role_deleted';
}
