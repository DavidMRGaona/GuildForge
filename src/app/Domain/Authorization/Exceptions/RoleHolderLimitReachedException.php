<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Exceptions;

use DomainException;

/**
 * A role with a holder limit already has as many holders as it allows: someone must give it
 * up before another person can get it.
 */
final class RoleHolderLimitReachedException extends DomainException
{
    /**
     * @param  list<string>  $holderNames  the visible names of whoever holds the role now
     */
    public static function forRole(string $roleDisplayName, array $holderNames): self
    {
        return new self(trans_choice('authorization.errors.role_holder_limit', count($holderNames), [
            'role' => $roleDisplayName,
            'holders' => implode(', ', $holderNames),
        ]));
    }
}
