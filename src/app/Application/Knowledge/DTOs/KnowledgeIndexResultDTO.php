<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class KnowledgeIndexResultDTO
{
    public function __construct(
        public int $indexed,
        public int $removed,
        public ?string $error,
    ) {}
}
