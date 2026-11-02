<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs\Response;

final readonly class KnowledgeCategoryPageResponseDTO
{
    /**
     * @param  list<KnowledgeGuideSummaryDTO>  $guides
     */
    public function __construct(
        public KnowledgeCategoryResponseDTO $category,
        public array $guides,
    ) {}

    /**
     * @return array{category: array{id: string, label: string, icon: string, guideCount: int}, guides: list<array{id: string, title: string, summary: string, categoryId: string, badge: 'new'|'updated'|null}>}
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category->toArray(),
            'guides' => array_map(static fn (KnowledgeGuideSummaryDTO $g): array => $g->toArray(), $this->guides),
        ];
    }
}
