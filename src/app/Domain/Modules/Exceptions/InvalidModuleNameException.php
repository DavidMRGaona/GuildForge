<?php

declare(strict_types=1);

namespace App\Domain\Modules\Exceptions;

use DomainException;

final class InvalidModuleNameException extends DomainException
{
    public static function empty(): self
    {
        return new self('Module name cannot be empty.');
    }

    public static function invalidFormat(string $value): self
    {
        return new self(
            "Invalid module name format: '{$value}'. Module name must be in kebab-case format (lowercase letters, numbers, and hyphens), cannot start or end with a hyphen, cannot have consecutive hyphens, and cannot start with a number."
        );
    }
}
