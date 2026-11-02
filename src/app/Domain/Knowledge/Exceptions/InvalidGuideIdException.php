<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Exceptions;

use DomainException;

final class InvalidGuideIdException extends DomainException
{
    public static function invalidFormat(string $value): self
    {
        return new self(sprintf(
            "Invalid guide id: '%s'. Expected lowercase kebab-case segments separated by dots, e.g. 'game-tables.join-table'.",
            $value,
        ));
    }
}
