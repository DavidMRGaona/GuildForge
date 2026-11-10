<?php

declare(strict_types=1);

namespace App\Domain\Events;

/**
 * Roles of an account turned on or off without being granted or taken away: they require the
 * association member role, and the account became (or stopped being) a member because the
 * association member role setting changed, or because a role gained or lost that requirement.
 * Dispatched after the change commits, once per affected account.
 *
 * The roles stored for the account do not change, so UserRolesChanged does not come through;
 * the effective permissions do. Role names, sorted. A role that is both activated and paused
 * never appears.
 */
final readonly class UserActiveRolesChanged
{
    /**
     * @param  list<string>  $rolesActivated  roles that count for the account now and did not before
     * @param  list<string>  $rolesPaused  roles that counted for the account and do not now
     */
    public function __construct(
        public string $userId,
        public array $rolesActivated,
        public array $rolesPaused,
    ) {}
}
