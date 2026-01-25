<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Exceptions;

use DomainException;

final class InvalidPermissionKeyException extends DomainException
{
    public static function empty(): self
    {
        return new self('Permission key cannot be empty.');
    }

    public static function invalidFormat(string $value): self
    {
        return new self(
            sprintf(
                "Invalid permission key format: '%s'. Permission key must be in format 'resource.action' or 'module:resource.action'.",
                $value
            )
        );
    }
}
