<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

final class InvalidHexColorException extends DomainException
{
    public static function empty(): self
    {
        return new self('Hex color value cannot be empty');
    }

    public static function invalidFormat(string $value): self
    {
        return new self("Invalid hex color format: {$value}");
    }
}
