<?php

declare(strict_types=1);

namespace App\Domain\Modules\Exceptions;

use DomainException;

final class InvalidModuleVersionException extends DomainException
{
    public static function invalidFormat(string $value): self
    {
        return new self(
            "Invalid module version format: '{$value}'. Version must be in semantic versioning format (e.g., '1.2.3')."
        );
    }
}
