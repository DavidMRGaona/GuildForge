<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class KnowledgeLintIssueDTO
{
    public function __construct(
        public string $file,
        public string $message,
    ) {}
}
