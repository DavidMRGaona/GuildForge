<?php

declare(strict_types=1);

namespace App\Domain\Modules\Exceptions;

use DomainException;

final class ModuleAlreadyDisabledException extends DomainException
{
    public static function withName(string $name): self
    {
        return new self("Module \"{$name}\" is already disabled.");
    }
}
