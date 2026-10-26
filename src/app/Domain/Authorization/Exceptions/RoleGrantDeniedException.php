<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Exceptions;

use DomainException;

/**
 * A change of roles touches a role the actor cannot grant: nobody gives or takes a role whose
 * permissions they do not hold themselves.
 */
final class RoleGrantDeniedException extends DomainException
{
    /**
     * @param  list<string>  $roleDisplayNames  the visible names of the roles the actor cannot grant
     */
    public static function forRoles(array $roleDisplayNames): self
    {
        return new self(__('authorization.errors.role_grant_denied', [
            'roles' => implode(', ', $roleDisplayNames),
        ]));
    }
}
