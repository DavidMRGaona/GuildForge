<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\ValueObjects;

use App\Domain\Knowledge\Exceptions\InvalidGuideIdException;
use Stringable;

final readonly class GuideId implements Stringable
{
    private const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\.[a-z0-9]+(?:-[a-z0-9]+)*)+$/';

    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidGuideIdException::invalidFormat($value);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function prefix(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
