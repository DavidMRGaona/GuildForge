<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\Exceptions;

use DomainException;

final class InvalidGuideDocumentException extends DomainException
{
    public static function because(string $reason): self
    {
        return new self('Invalid guide document: '.$reason);
    }
}
