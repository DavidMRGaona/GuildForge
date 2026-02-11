<?php

declare(strict_types=1);

namespace App\Domain\Mail\ValueObjects;

use App\Domain\Mail\Exceptions\InvalidSmtpPortException;

final readonly class SmtpPort
{
    public function __construct(
        private int $value,
    ) {
        if ($value < 1 || $value > 65535) {
            throw InvalidSmtpPortException::outOfRange($value);
        }
    }

    public static function default(): self
    {
        return new self(587);
    }

    public static function ssl(): self
    {
        return new self(465);
    }

    public static function plain(): self
    {
        return new self(25);
    }

    public function value(): int
    {
        return $this->value;
    }
}
