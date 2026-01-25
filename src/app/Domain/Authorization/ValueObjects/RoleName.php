<?php

declare(strict_types=1);

namespace App\Domain\Authorization\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class RoleName implements Stringable
{
    private const int MIN_LENGTH = 2;

    private const int MAX_LENGTH = 50;

    public function __construct(
        public string $value,
    ) {
        $this->validate($value);
    }

    public static function fromString(string $value): static
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private function validate(string $value): void
    {
        if ($value === '') {
            throw new InvalidArgumentException('Role name cannot be empty');
        }

        $length = mb_strlen($value);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Role name must be between %d and %d characters', self::MIN_LENGTH, self::MAX_LENGTH)
            );
        }

        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $value)) {
            throw new InvalidArgumentException('Role name must be in kebab-case format');
        }
    }
}
