<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs\Response;

final readonly class KnowledgeSearchHitDTO
{
    /**
     * @param  list<array{text: string, match: bool}>  $snippet  plain text segments, never HTML
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $categoryId,
        public array $snippet,
    ) {}
}
