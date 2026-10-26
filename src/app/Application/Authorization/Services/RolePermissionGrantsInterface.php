<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;

/**
 * Grants permissions to roles from a migration (a module's included), with an entry in the
 * role history like a change made in the role editor. Never writes role_permission by hand.
 *
 * Grants only add: what a role holds is never taken away, and the history lists only the
 * permissions the role did not have. A permission row that does not exist yet (migrations
 * run before permissions:sync) is created from its definition; an existing row is left as
 * it is. The authorization cache is invalidated once the transaction commits, and nothing
 * is written when no role gets the grant.
 */
interface RolePermissionGrantsInterface
{
    /**
     * Grants the permissions to the role with that name; does nothing when it does not exist.
     */
    public function grantToRole(string $roleName, PermissionDefinitionDTO ...$permissions): void;

    /**
     * Grants the permissions to every role that holds $heldPermissionKey, e.g. when a
     * permission is split in finer ones and nobody should lose an action.
     */
    public function grantToRolesWith(string $heldPermissionKey, PermissionDefinitionDTO ...$permissions): void;
}
