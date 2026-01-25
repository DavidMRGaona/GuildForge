<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\ValueObjects\UserId;
use DateTimeImmutable;

final class User
{
    public function __construct(
        private readonly UserId $id,
        private readonly string $name,
        private readonly string $email,
        private readonly ?string $displayName = null,
        private readonly ?string $pendingEmail = null,
        private readonly ?string $avatarPublicId = null,
        private readonly ?DateTimeImmutable $emailVerifiedAt = null,
        private readonly ?DateTimeImmutable $anonymizedAt = null,
        private readonly ?DateTimeImmutable $createdAt = null,
        private readonly ?DateTimeImmutable $updatedAt = null,
    ) {
    }

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
        return $this->emailVerifiedAt !== null;
    }

    public function isAnonymized(): bool
    {
        return $this->anonymizedAt !== null;
    }

    public function getDisplayableName(): string
    {
        return $this->displayName ?? $this->name;
    }
}
