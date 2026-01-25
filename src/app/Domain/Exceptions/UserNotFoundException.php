<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use Exception;

final class UserNotFoundException extends Exception
{
    public static function withId(string $userId): self
    {
        return new self("User not found: $userId");
    }
}
