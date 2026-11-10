<?php

declare(strict_types=1);

namespace App\Domain\Media\Exceptions;

use DomainException;

final class InvalidMediaFileIdException extends DomainException
{
    public static function invalid(string $value): self
    {
        return new self(sprintf(
            "Invalid media file id: '%s'. It must not be empty, start or end with '/', or contain '..'.",
            $value,
        ));
    }
}
