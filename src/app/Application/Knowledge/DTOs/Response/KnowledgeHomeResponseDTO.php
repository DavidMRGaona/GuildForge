<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs\Response;

final readonly class KnowledgeHomeResponseDTO
{
    /**
     * @param  list<KnowledgeCategoryResponseDTO>  $categories
     * @param  list<KnowledgeGuideSummaryDTO>  $featured  starred guides first, then new ones
     */
    public function __construct(
        public array $categories,
        public array $featured,
    ) {}

    /**
     * @return array{categories: list<array{id: string, label: string, icon: string, guideCount: int}>, featured: list<array{id: string, title: string, summary: string, categoryId: string, badge: 'new'|'updated'|null}>}
     */
    public function toArray(): array
    {
        return [
            'categories' => array_map(static fn (KnowledgeCategoryResponseDTO $c): array => $c->toArray(), $this->categories),
            'featured' => array_map(static fn (KnowledgeGuideSummaryDTO $g): array => $g->toArray(), $this->featured),
        ];
    }
}
