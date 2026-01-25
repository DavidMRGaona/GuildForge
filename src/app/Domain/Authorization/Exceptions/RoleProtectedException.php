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

    public static function cannotModify(string $roleName): self
    {
        return new self("Cannot modify protected role: {$roleName}");
    }
}
