<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

use App\Domain\Authorization\Exceptions\RoleNotFoundException;

/**
 * "member" is the base role of every account (not the association member role): modules
 * grant it permissions, so every way of creating a user must give it through this service.
 */
interface MemberRoleServiceInterface
{
    /**
     * Give the member role to a new account, keeping its other roles. When the role does
     * not exist (an install without seeders) the account is still created: a warning is
     * logged and nothing else happens.
     */
    public function assignTo(object $user): void;

    /**
     * Give the member role to every user without it (soft-deleted ones included, anonymized
     * ones excluded: anonymizing removes every role), never removing a role, in batches.
     * Returns how many users got it (or would get it, on a dry run).
     *
     * @param  positive-int  $chunkSize
     *
     * @throws RoleNotFoundException When the member role does not exist
     */
    public function assignToAllUsers(bool $dryRun = false, int $chunkSize = 500): int;
}
