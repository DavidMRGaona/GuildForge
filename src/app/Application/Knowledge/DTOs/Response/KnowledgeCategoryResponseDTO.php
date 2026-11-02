<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs\Response;

final readonly class KnowledgeCategoryResponseDTO
{
    public function __construct(
        public string $id,
        public string $label,
        public string $icon,
        public int $guideCount,
    ) {}

    /**
     * @return array{id: string, label: string, icon: string, guideCount: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'icon' => $this->icon,
            'guideCount' => $this->guideCount,
        ];
    }
}
