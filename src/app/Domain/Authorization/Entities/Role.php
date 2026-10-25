<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Entities;

use App\Domain\Authorization\ValueObjects\RoleId;
use App\Domain\Authorization\ValueObjects\RoleName;

final readonly class Role
{
    public function __construct(
        private RoleId $id,
        private RoleName $name,
        private string $displayName,
        private ?string $description = null,
        private bool $isProtected = false,
    ) {}

    public function id(): RoleId
    {
        return $this->id;
    }

    public function name(): RoleName
    {
        return $this->name;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function isProtected(): bool
    {
        return $this->isProtected;
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
