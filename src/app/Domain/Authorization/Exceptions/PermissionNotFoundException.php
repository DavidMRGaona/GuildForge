<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Exceptions;

use DomainException;

final class PermissionNotFoundException extends DomainException
{
    public static function withKey(string $key): self
    {
        return new self("Permission not found with key: {$key}");
    }
}
