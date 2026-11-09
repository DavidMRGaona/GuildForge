<?php

declare(strict_types=1);

namespace App\Domain\Events;

/**
 * A deactivated account was restored. Dispatched after the change commits, before the roles
 * that filled up while it was away are dropped (that comes as a UserRolesChanged).
 */
final readonly class UserRestored
{
    public function __construct(
        public string $userId,
    ) {}
}
