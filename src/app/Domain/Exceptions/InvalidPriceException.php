<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

final class InvalidPriceException extends DomainException
{
    public static function create(): self
    {
        return new self('Price cannot be negative');
    }
}
