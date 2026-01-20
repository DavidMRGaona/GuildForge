<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Exceptions\InvalidSlugException;
use Stringable;

final readonly class Slug implements Stringable
{
    private const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        public string $value,
    ) {
        $this->validate($value);
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
            throw InvalidSlugException::empty();
        }

        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidSlugException::invalidFormat($value);
        }
    }
}
