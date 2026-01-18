<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

final class CannotPublishPastEventException extends DomainException
{
    public static function create(): self
    {
        return new self('Cannot publish an event that has already passed.');
    }
}
