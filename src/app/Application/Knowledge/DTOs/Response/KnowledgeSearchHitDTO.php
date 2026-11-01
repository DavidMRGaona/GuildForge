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

    /**
     * @return array{id: string, title: string, categoryId: string, snippet: list<array{text: string, match: bool}>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'categoryId' => $this->categoryId,
            'snippet' => $this->snippet,
        ];
    }
}
