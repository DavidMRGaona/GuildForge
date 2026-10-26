<?php

declare(strict_types=1);

namespace App\Domain\Events;

/**
 * An account stopped holding the association member role while still holding roles that
 * require it, so those roles are inactive now. Dispatched after the change commits.
 *
 * The roles are carried by name (stable keys); the listener resolves their display names.
 */
final readonly class AssociationRoleLost
{
    /**
     * @param  list<string>  $inactiveRoleNames
     */
    public function __construct(
        public string $userId,
        public array $inactiveRoleNames,
    ) {}
}
