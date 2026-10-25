<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\UserId;
use DateTimeImmutable;

final readonly class User
{
    public function __construct(
        private UserId $id,
        private string $name,
        private string $email,
        private ?string $displayName = null,
        private ?string $pendingEmail = null,
        private ?string $avatarPublicId = null,
        private ?DateTimeImmutable $emailVerifiedAt = null,
        private ?DateTimeImmutable $anonymizedAt = null,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
    ) {}

    public function id(): UserId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function displayName(): ?string
    {
        return $this->displayName;
    }

    public function pendingEmail(): ?string
    {
        return $this->pendingEmail;
    }

    public function avatarPublicId(): ?string
    {
        return $this->avatarPublicId;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function anonymizedAt(): ?DateTimeImmutable
    {
        return $this->anonymizedAt;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt instanceof DateTimeImmutable;
    }

    public function isAnonymized(): bool
    {
        return $this->anonymizedAt instanceof DateTimeImmutable;
    }

    public function getDisplayableName(): string
    {
        return $this->displayName ?? $this->name;
    }
}
