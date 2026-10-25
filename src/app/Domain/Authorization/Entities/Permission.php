<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Entities;

use App\Domain\Authorization\ValueObjects\PermissionId;
use App\Domain\Authorization\ValueObjects\PermissionKey;

final readonly class Permission
{
    public function __construct(
        private PermissionId $id,
        private PermissionKey $key,
        private string $label,
        private string $resource,
        private string $action,
        private ?string $module = null,
    ) {}

    public function id(): PermissionId
    {
        return $this->id;
    }

    public function key(): PermissionKey
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
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

    public function belongsToModule(): bool
    {
        return $this->module !== null;
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
