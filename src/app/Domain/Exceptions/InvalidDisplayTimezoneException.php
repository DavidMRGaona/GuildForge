<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use RuntimeException;

final class InvalidDisplayTimezoneException extends RuntimeException
{
    public static function unknown(string $zone): self
    {
        return new self(sprintf('Invalid display timezone "%s": use an IANA identifier such as Europe/Madrid.', $zone));
    }
}
