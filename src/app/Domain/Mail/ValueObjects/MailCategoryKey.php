<?php

declare(strict_types=1);

namespace App\Domain\Mail\ValueObjects;

use App\Domain\Mail\Exceptions\InvalidMailCategoryException;
use Stringable;

/**
 * Stable key of a mail category, "<prefix>.<name>": the prefix is "core" or the module name
 * (e.g. "game-tables.reminders"). Stored with each person's preferences, so it never changes.
 */
final readonly class MailCategoryKey implements Stringable
{
    private const string PATTERN = '/^([a-z0-9]+(?:-[a-z0-9]+)*)\.([a-z0-9]+(?:-[a-z0-9]+)*)$/';

    private string $prefix;

    private string $name;

    public function __construct(
        public string $value,
    ) {
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            throw InvalidMailCategoryException::invalidKey($value);
        }

        [, $this->prefix, $this->name] = $matches;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
