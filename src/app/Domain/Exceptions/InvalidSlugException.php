<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use InvalidArgumentException;

final class InvalidSlugException extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('Slug cannot be empty.');
    }

    public static function invalidFormat(string $value): self
    {
        return new self(
            "Invalid slug format: '{$value}'. Slug must contain only lowercase letters, numbers, and hyphens, and cannot start or end with a hyphen."
        );
    }
}
