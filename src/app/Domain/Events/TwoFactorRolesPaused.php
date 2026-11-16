<?php

declare(strict_types=1);

namespace App\Domain\Events;

/**
 * Roles of an account went into pause because they require two-factor authentication and the
 * account has it off (core#772): the role started requiring it, the account was given the role,
 * or it turned two-factor authentication off or had it reset. Dispatched after the change
 * commits, for the core's notice to the person; modules follow the effective roles through
 * UserActiveRolesChanged and UserRolesChanged instead.
 *
 * The roles are carried by name (stable keys); the listener resolves their display names.
 */
final readonly class TwoFactorRolesPaused
{
    /**
     * @param  list<string>  $roleNames  sorted
     */
    public function __construct(
        public string $userId,
        public array $roleNames,
    ) {}
}
