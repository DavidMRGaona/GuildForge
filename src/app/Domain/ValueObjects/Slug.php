<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Exceptions\InvalidSlugException;
use Illuminate\Support\Str;
use Stringable;

final readonly class Slug implements Stringable
{
    private const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        public string $value,
    ) {
        $this->validate($value);
    }

    public static function fromTitleAndUuid(string $title, string $uuid, int $uuidLength = 8): self
    {
        $baseSlug = Str::slug($title);

        if ($baseSlug === '') {
            $baseSlug = 'item';
        }

        $shortUuid = substr($uuid, 0, $uuidLength);

        return new self("{$baseSlug}-{$shortUuid}");
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
