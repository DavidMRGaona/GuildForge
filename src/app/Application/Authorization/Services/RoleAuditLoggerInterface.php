<?php

declare(strict_types=1);

namespace App\Application\Authorization\Services;

use App\Domain\Authorization\Enums\RoleAuditEvent;

/**
 * Writes the role history: every role given to or taken from an account and every change to
 * a role's definition, with who made it (the signed-in user, null when nobody is) and from
 * where (RoleAuditSource).
 *
 * Call it inside the transaction that makes the change, and only for a real change: an entry
 * that cannot be written throws, so the change is rolled back with it.
 */
interface RoleAuditLoggerInterface
{
    public function roleAssigned(object $user, string $roleName): void;

    /**
     * The same role given to several accounts at once: one insert for all of them.
     *
     * @param  list<string>  $userIds
     */
    public function roleAssignedToMany(array $userIds, string $roleName): void;

    public function roleRemoved(object $user, string $roleName): void;

    /**
     * A change to a role's definition. For RoleDeleted, call it before the role is deleted:
     * the role is taken from every account that holds it, and each of them gets a removed
     * entry as well.
     *
     * @param  array{permissions_added?: list<string>, permissions_removed?: list<string>, fields?: array<string, array{from: mixed, to: mixed}>}  $changes
     */
    public function roleChanged(string $roleId, string $roleName, RoleAuditEvent $event, array $changes): void;
}
