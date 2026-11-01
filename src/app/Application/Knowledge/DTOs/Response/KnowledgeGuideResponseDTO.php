<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs\Response;

final readonly class KnowledgeGuideResponseDTO
{
    /**
     * @param  'new'|'updated'|null  $badge
     * @param  list<KnowledgeGuideSummaryDTO>  $related  only guides the user may see
     * @param  list<KnowledgeGuideSummaryDTO>  $siblings  visible guides of the same category
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $summary,
        public KnowledgeCategoryResponseDTO $category,
        public string $html,
        public ?string $badge,
        public array $related,
        public array $siblings,
    ) {}

    /**
     * @return array{id: string, title: string, summary: string, category: array{id: string, label: string, icon: string, guideCount: int}, html: string, badge: 'new'|'updated'|null, related: list<array{id: string, title: string, summary: string, categoryId: string, badge: 'new'|'updated'|null}>, siblings: list<array{id: string, title: string, summary: string, categoryId: string, badge: 'new'|'updated'|null}>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'category' => $this->category->toArray(),
            'html' => $this->html,
            'badge' => $this->badge,
            'related' => array_map(static fn (KnowledgeGuideSummaryDTO $g): array => $g->toArray(), $this->related),
            'siblings' => array_map(static fn (KnowledgeGuideSummaryDTO $g): array => $g->toArray(), $this->siblings),
        ];
    }
}
