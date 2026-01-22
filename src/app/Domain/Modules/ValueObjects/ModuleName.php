<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use App\Domain\Modules\Exceptions\InvalidModuleNameException;
use Stringable;

final readonly class ModuleName implements Stringable
{
    private const string PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    public function __construct(
        public string $value,
    ) {
        $this->validate($value);
    }

    public static function fromString(string $name): self
    {
        return new self($name);
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

    public function toStudlyCase(): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $this->value)));
    }

    private function validate(string $value): void
    {
        if ($value === '') {
            throw InvalidModuleNameException::empty();
        }

        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidModuleNameException::invalidFormat($value);
        }
    }
}
