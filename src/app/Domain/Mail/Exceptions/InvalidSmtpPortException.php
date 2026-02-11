<?php

declare(strict_types=1);

namespace App\Domain\Mail\Exceptions;

use DomainException;

final class InvalidSmtpPortException extends DomainException
{
    public static function outOfRange(int $port): self
    {
        return new self("SMTP port must be between 1 and 65535, got: {$port}.");
    }
}
