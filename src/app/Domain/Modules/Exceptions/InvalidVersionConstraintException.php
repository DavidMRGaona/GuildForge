<?php

declare(strict_types=1);

namespace App\Domain\Modules\Exceptions;

use InvalidArgumentException;

final class InvalidVersionConstraintException extends InvalidArgumentException
{
    public static function invalid(string $constraint, string $reason): self
    {
        return new self("Invalid version constraint '{$constraint}': {$reason}");
    }
}
