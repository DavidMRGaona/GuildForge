<?php

declare(strict_types=1);

namespace App\Domain\Profiles\Exceptions;

use DomainException;

final class InvalidProfileHandleException extends DomainException
{
    private function __construct(
        string $message,
        public readonly bool $reserved,
    ) {
        parent::__construct($message);
    }

    public static function malformed(): self
    {
        return new self('A profile address has 3 to 30 lowercase letters, digits and single hyphens between them.', false);
    }

    public static function reserved(): self
    {
        return new self('This word is reserved and cannot be a profile address.', true);
    }
}
