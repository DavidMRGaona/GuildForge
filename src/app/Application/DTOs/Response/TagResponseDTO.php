<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

final readonly class TagResponseDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $parentId,
        public ?string $parentName,
        /** @var array<string> */
        public array $appliesTo,
        public string $color,
        public int $sortOrder,
    ) {}
}
