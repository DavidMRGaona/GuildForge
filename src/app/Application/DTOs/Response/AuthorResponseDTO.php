<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

final readonly class AuthorResponseDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $displayName,
        public ?string $avatarPublicId,
    ) {
    }
}
