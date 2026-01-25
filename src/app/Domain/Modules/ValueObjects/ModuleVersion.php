<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use App\Domain\Modules\Exceptions\InvalidModuleVersionException;
use Stringable;

final readonly class ModuleVersion implements Stringable
{
    private const string SEMVER_PATTERN = '/^(\d+)\.(\d+)\.(\d+)$/';

    public function __construct(
        public int $major,
        public int $minor,
        public int $patch,
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
        );
    }

    public function value(): string
    {
        return "{$this->major}.{$this->minor}.{$this->patch}";
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

    public function satisfies(string $constraint): bool
    {
        // Caret constraint (^1.0 or ^1.2)
        if (str_starts_with($constraint, '^')) {
            return $this->satisfiesCaretConstraint(substr($constraint, 1));
        }

        // Greater than or equal constraint (>=1.2.0)
        if (str_starts_with($constraint, '>=')) {
            return $this->satisfiesGreaterThanOrEqualConstraint(substr($constraint, 2));
        }

        // Tilde constraint (~1.2)
        if (str_starts_with($constraint, '~')) {
            return $this->satisfiesTildeConstraint(substr($constraint, 1));
        }

        // Exact version match (1.0.0)
        return $this->satisfiesExactConstraint($constraint);
    }

    private function compare(self $other): int
    {
        if ($this->major !== $other->major) {
            return $this->major <=> $other->major;
        }

        if ($this->minor !== $other->minor) {
            return $this->minor <=> $other->minor;
        }

        return $this->patch <=> $other->patch;
    }

    private function satisfiesCaretConstraint(string $constraint): bool
    {
        $parts = explode('.', $constraint);
        $constraintMajor = (int) $parts[0];
        $constraintMinor = isset($parts[1]) ? (int) $parts[1] : 0;

        // Must be same major version
        if ($this->major !== $constraintMajor) {
            return false;
        }

        // Must be >= the minor version specified
        if ($this->minor < $constraintMinor) {
            return false;
        }

        return true;
    }

    private function satisfiesGreaterThanOrEqualConstraint(string $constraint): bool
    {
        $normalizedConstraint = $this->normalizeVersionString($constraint);
        $constraintVersion = self::fromString($normalizedConstraint);

        return $this->isGreaterThanOrEqual($constraintVersion);
    }

    private function normalizeVersionString(string $version): string
    {
        // Already in X.Y.Z format
        if (preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return $version;
        }

        // X.Y format - add .0
        if (preg_match('/^(\d+)\.(\d+)$/', $version, $matches)) {
            return "{$matches[1]}.{$matches[2]}.0";
        }

        // X format - add .0.0
        if (preg_match('/^(\d+)$/', $version, $matches)) {
            return "{$matches[1]}.0.0";
        }

        return $version;
    }

    private function satisfiesTildeConstraint(string $constraint): bool
    {
        $parts = explode('.', $constraint);
        $constraintMajor = (int) $parts[0];
        $constraintMinor = isset($parts[1]) ? (int) $parts[1] : 0;

        // Must be same major and minor version
        return $this->major === $constraintMajor && $this->minor === $constraintMinor;
    }

    private function satisfiesExactConstraint(string $constraint): bool
    {
        $constraintVersion = self::fromString($constraint);

        return $this->isEqualTo($constraintVersion);
    }
}
