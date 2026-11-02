<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs\Response;

final readonly class KnowledgeGuideSummaryDTO
{
    /**
     * @param  'new'|'updated'|null  $badge
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $summary,
        public string $categoryId,
        public ?string $badge,
    ) {}

    /**
     * @return array{id: string, title: string, summary: string, categoryId: string, badge: 'new'|'updated'|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'categoryId' => $this->categoryId,
            'badge' => $this->badge,
        ];
    }
}
