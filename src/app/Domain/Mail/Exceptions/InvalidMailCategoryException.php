<?php

declare(strict_types=1);

namespace App\Domain\Mail\Exceptions;

use DomainException;

final class InvalidMailCategoryException extends DomainException
{
    public static function invalidKey(string $key): self
    {
        return new self(sprintf(
            'The mail category "%s" must be in the form "prefix.name", in lowercase letters, digits and hyphens (for example "game-tables.reminders").',
            $key,
        ));
    }

    public static function wrongPrefix(string $key, string $prefix): self
    {
        return new self(sprintf('The mail category "%s" must start with "%s.".', $key, $prefix));
    }

    public static function mandatoryWithoutReason(string $key): self
    {
        return new self(sprintf('The mandatory mail category "%s" needs the reason it is always sent.', $key));
    }
}
