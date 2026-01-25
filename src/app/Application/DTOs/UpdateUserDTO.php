<?php

declare(strict_types=1);

namespace App\Application\DTOs;

final readonly class UpdateUserDTO
{
    public function __construct(
        public string $name,
        public ?string $displayName,
        public string $email,
        public ?string $avatarPublicId = null,
    ) {
    }

    /**
     * @param  array{name: string, display_name?: string|null, email: string, avatar_public_id?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            displayName: $data['display_name'] ?? null,
            email: $data['email'],
            avatarPublicId: $data['avatar_public_id'] ?? null,
        );
    }
}
