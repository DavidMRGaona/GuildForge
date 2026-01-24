<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

final readonly class PhotoResponseDTO
{
    public function __construct(
        public string $id,
        public string $imagePublicId,
        public ?string $caption,
        public int $sortOrder,
    ) {}
}
