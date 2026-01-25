<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Exceptions;

use DomainException;

final class InvalidRoleNameException extends DomainException
{
    public static function empty(): self
    {
        return new self('Role name cannot be empty.');
    }

    public static function tooShort(string $value, int $minLength): self
    {
        return new self(
            sprintf(
                "Role name '%s' is too short. Minimum length is %d characters.",
                $value,
                $minLength
            )
        );
    }

    public static function tooLong(string $value, int $maxLength): self
    {
        return new self(
            sprintf(
                "Role name '%s' is too long. Maximum length is %d characters.",
                $value,
                $maxLength
            )
        );
    }

    public static function invalidFormat(string $value): self
    {
        return new self(
            sprintf(
                "Invalid role name format: '%s'. Role name must be in kebab-case format (lowercase letters, numbers, and hyphens only, no leading/trailing hyphens).",
                $value
            )
        );
    }
}
