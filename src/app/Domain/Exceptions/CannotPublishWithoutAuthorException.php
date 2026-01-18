<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

final class CannotPublishWithoutAuthorException extends DomainException
{
    public static function create(): self
    {
        return new self('Cannot publish an article without an author.');
    }
}
