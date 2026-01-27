<?php

declare(strict_types=1);

namespace App\Application\Navigation\DTOs;

final readonly class MenuItemDTO
{
    /**
     * @param  array<MenuItemDTO>  $children
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $href,
        public string $target,
        public ?string $icon,
        public array $children = [],
        public bool $isActive = true,
    ) {
    }

    /**
     * Convert to array for Inertia sharing.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'href' => $this->href,
            'target' => $this->target,
            'icon' => $this->icon,
            'children' => array_map(
                fn (MenuItemDTO $child): array => $child->toArray(),
                $this->children
            ),
            'isActive' => $this->isActive,
        ];
    }
}
