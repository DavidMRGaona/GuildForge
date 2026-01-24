<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

final readonly class HeroSlideResponseDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $subtitle,
        public ?string $buttonText,
        public ?string $buttonUrl,
        public string $imagePublicId,
        public bool $isActive,
        public int $sortOrder,
    ) {}
}
