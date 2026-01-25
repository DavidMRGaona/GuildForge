<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Entities;

use App\Domain\Authorization\ValueObjects\RoleId;
use App\Domain\Authorization\ValueObjects\RoleName;

final class Role
{
    public function __construct(
        private readonly RoleId $id,
        private readonly RoleName $name,
        private readonly string $displayName,
        private readonly ?string $description = null,
        private readonly bool $isProtected = false,
    ) {
    }

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
