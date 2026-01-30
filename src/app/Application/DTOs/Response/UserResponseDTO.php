<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

use DateTimeImmutable;
use JsonSerializable;

final readonly class UserResponseDTO implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $displayName,
        public string $email,
        public ?string $pendingEmail,
        public ?string $avatarPublicId,
        public string $role,
        public bool $emailVerified,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'displayName' => $this->displayName,
            'email' => $this->email,
            'pendingEmail' => $this->pendingEmail,
            'avatarPublicId' => $this->avatarPublicId,
            'role' => $this->role,
            'emailVerified' => $this->emailVerified,
            'createdAt' => $this->createdAt->format('c'),
        ];
    }
}
