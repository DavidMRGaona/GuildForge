<?php

declare(strict_types=1);

namespace App\Domain\Authorization\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class PermissionKey implements Stringable
{
    private ?string $module;

    private string $resource;

    private string $action;

    public function __construct(
        public string $value,
    ) {
        $this->validate($value);

        [$this->module, $this->resource, $this->action] = $this->parse($value);
    }

    public static function fromString(string $value): static
    {
        return new self($value);
    }

    public function resource(): string
    {
        return $this->resource;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function module(): ?string
    {
        return $this->module;
    }

    public function isModulePermission(): bool
    {
        return $this->module !== null;
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
            throw new InvalidArgumentException('Permission key cannot be empty');
        }

        // Check for module:resource.action or resource.action format
        // Allows lowercase letters, numbers, hyphens, and underscores
        $pattern = '/^(?:([a-z0-9_-]+):)?([a-z0-9_-]+)\.([a-z0-9_-]+)$/';

        if (! preg_match($pattern, $value, $matches)) {
            throw new InvalidArgumentException(
                'Permission key must be in format "resource.action" or "module:resource.action"'
            );
        }
    }

    /**
     * @return array{0: ?string, 1: string, 2: string}
     */
    private function parse(string $value): array
    {
        $pattern = '/^(?:([a-z0-9_-]+):)?([a-z0-9_-]+)\.([a-z0-9_-]+)$/';
        preg_match($pattern, $value, $matches);

        $module = $matches[1] !== '' ? $matches[1] : null;
        $resource = $matches[2];
        $action = $matches[3];

        return [$module, $resource, $action];
    }
}
