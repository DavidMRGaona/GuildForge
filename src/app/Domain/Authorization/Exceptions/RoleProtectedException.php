<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Exceptions;

use DomainException;

final class RoleProtectedException extends DomainException
{
    public static function cannotDelete(string $roleName): self
    {
        return new self("Cannot delete protected role: {$roleName}");
    }

    public static function cannotDeleteAssociationMemberRole(string $roleName): self
    {
        return new self("Cannot delete the association member role: {$roleName}");
    }

    /**
     * The base role of every account ("member"): deleting it would strip every account of it.
     */
    public static function cannotDeleteBaseRole(string $roleName): self
    {
        return new self(__('authorization.errors.cannot_delete_base_role', ['role' => $roleName]));
    }

    public static function cannotModify(string $roleName): self
    {
        return new self("Cannot modify protected role: {$roleName}");
    }
}
