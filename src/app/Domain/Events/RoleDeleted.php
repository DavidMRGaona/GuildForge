<?php

declare(strict_types=1);

namespace App\Domain\Events;

/**
 * A role was deleted, so every account that held it lost it. Dispatched after the deletion
 * commits, once per deletion.
 *
 * It carries the role as it was and the ids of the accounts that held it (deactivated ones
 * included; anonymized accounts hold no roles), so a module can revoke what the role granted
 * without one event per holder. Those accounts get no UserRolesChanged for this.
 */
final readonly class RoleDeleted
{
    /**
     * @param  list<string>  $permissions  permission keys the role carried, sorted
     * @param  list<string>  $holderIds
     */
    public function __construct(
        public string $roleId,
        public string $roleName,
        public array $permissions,
        public array $holderIds,
    ) {}
}
