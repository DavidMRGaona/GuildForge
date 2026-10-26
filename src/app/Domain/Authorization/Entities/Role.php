<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Entities;

use App\Domain\Authorization\ValueObjects\RoleId;
use App\Domain\Authorization\ValueObjects\RoleName;
use InvalidArgumentException;

final readonly class Role
{
    public function __construct(
        private RoleId $id,
        private RoleName $name,
        private string $displayName,
        private ?string $description = null,
        private bool $isProtected = false,
        private bool $requiresAssociationMember = false,
        private ?int $maxHolders = null,
    ) {
        if ($maxHolders !== null && $maxHolders < 1) {
            throw new InvalidArgumentException('The maximum number of holders must be at least 1.');
        }
    }

    public function requiresAssociationMember(): bool
    {
        return $this->requiresAssociationMember;
    }

    public function maxHolders(): ?int
    {
        return $this->maxHolders;
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
