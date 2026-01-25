<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

use DateTimeImmutable;

final readonly class UserResponseDTO
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
}
