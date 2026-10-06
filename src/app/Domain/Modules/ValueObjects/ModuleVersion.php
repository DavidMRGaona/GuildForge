<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use App\Domain\Modules\Exceptions\InvalidModuleVersionException;
use App\Domain\Modules\Exceptions\InvalidVersionConstraintException;
use App\Domain\Modules\Services\ConstraintMatcher;
use Stringable;

final readonly class ModuleVersion implements Stringable
{
    private const string SEMVER_PATTERN = '/^(\d+)\.(\d+)\.(\d+)(?:-([a-zA-Z0-9]+(?:\.[a-zA-Z0-9]+)*))?$/';

    public function __construct(
        public int $major,
        public int $minor,
        public int $patch,
        public ?string $preRelease = null,
    ) {
    }

    public static function fromString(string $version): self
    {
        if (preg_match(self::SEMVER_PATTERN, $version, $matches) !== 1) {
            throw InvalidModuleVersionException::invalidFormat($version);
        }

        return new self(
            major: (int) $matches[1],
            minor: (int) $matches[2],
            patch: (int) $matches[3],
            preRelease: $matches[4] ?? null,
        );
    }

    public function value(): string
    {
        $version = "{$this->major}.{$this->minor}.{$this->patch}";

        if ($this->preRelease !== null) {
            $version .= "-{$this->preRelease}";
        }

        return $version;
    }

    public function __toString(): string
    {
        return $this->value();
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->compare($other) >= 0;
    }

    public function isLessThan(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function isEqualTo(self $other): bool
    {
        return $this->compare($other) === 0;
    }

    /**
     * False when the constraint is not valid: callers fail closed.
     */
    public function satisfies(string $constraint): bool
    {
        try {
            return (new ConstraintMatcher())->matches($constraint, $this);
        } catch (InvalidVersionConstraintException) {
            return false;
        }
    }

    /**
     * SemVer 2.0.0 §11 precedence.
     */
    private function compare(self $other): int
    {
        $core = [$this->major, $this->minor, $this->patch] <=> [$other->major, $other->minor, $other->patch];

        if ($core !== 0) {
            return $core;
        }

        if ($this->preRelease === $other->preRelease) {
            return 0;
        }

        // A version with pre-release has lower precedence than the release
        if ($this->preRelease === null) {
            return 1;
        }

        if ($other->preRelease === null) {
            return -1;
        }

        return self::comparePreRelease($this->preRelease, $other->preRelease);
    }

    private static function comparePreRelease(string $left, string $right): int
    {
        $leftIds = explode('.', $left);
        $rightIds = explode('.', $right);

        foreach ($leftIds as $index => $identifier) {
            if (! isset($rightIds[$index])) {
                return 1;
            }

            $result = self::compareIdentifier($identifier, $rightIds[$index]);

            if ($result !== 0) {
                return $result;
            }
        }

        return count($leftIds) <=> count($rightIds);
    }

    private static function compareIdentifier(string $left, string $right): int
    {
        $leftNumeric = ctype_digit($left);
        $rightNumeric = ctype_digit($right);

        if ($leftNumeric && $rightNumeric) {
            return (int) $left <=> (int) $right;
        }

        if ($leftNumeric) {
            return -1;
        }

        if ($rightNumeric) {
            return 1;
        }

        return strcmp($left, $right) <=> 0;
    }
}
