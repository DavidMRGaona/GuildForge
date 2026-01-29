<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

use DateTimeImmutable;

final readonly class LegalPageResponseDTO
{
    public function __construct(
        public string $title,
        public string $content,
        public ?DateTimeImmutable $lastUpdated,
    ) {
    }
}
