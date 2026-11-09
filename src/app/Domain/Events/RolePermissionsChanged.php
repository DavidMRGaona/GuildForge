<?php

declare(strict_types=1);

namespace App\Domain\Events;

/**
 * The permissions of a role changed. Dispatched after the change commits, once per change and
 * only when the set really changed (the same condition as the role history entry).
 *
 * Both lists are permission keys, sorted. Creating, renaming or deleting a role does not come
 * through here.
 */
final readonly class RolePermissionsChanged
{
    /**
     * @param  list<string>  $permissionsBefore
     * @param  list<string>  $permissionsAfter
     */
    public function __construct(
        public string $roleId,
        public string $roleName,
        public array $permissionsBefore,
        public array $permissionsAfter,
    ) {}
}
