<?php

declare(strict_types=1);

namespace App\Domain\Events;

/**
 * The roles an account holds changed. Dispatched after the change commits, once per change and
 * only when the set really changed.
 *
 * Both lists are the roles stored for the account, inactive ones included (as the role history
 * records them), by name and sorted. Anonymizing does not come through here: see UserAnonymized.
 */
final readonly class UserRolesChanged
{
    /**
     * @param  list<string>  $rolesBefore
     * @param  list<string>  $rolesAfter
     */
    public function __construct(
        public string $userId,
        public array $rolesBefore,
        public array $rolesAfter,
    ) {}
}
