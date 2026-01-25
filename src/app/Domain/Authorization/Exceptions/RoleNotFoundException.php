<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Exceptions;

use DomainException;

final class RoleNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self("Role not found with ID: {$id}");
    }

    public static function withName(string $name): self
    {
        return new self("Role not found with name: {$name}");
    }
}
