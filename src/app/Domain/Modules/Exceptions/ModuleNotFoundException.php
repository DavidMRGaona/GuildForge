<?php

declare(strict_types=1);

namespace App\Domain\Modules\Exceptions;

use DomainException;

final class ModuleNotFoundException extends DomainException
{
    public static function withName(string $name): self
    {
        return new self("Module '{$name}' not found.");
    }

    public static function withId(string $id): self
    {
        return new self("Module with ID '{$id}' not found.");
    }
}
