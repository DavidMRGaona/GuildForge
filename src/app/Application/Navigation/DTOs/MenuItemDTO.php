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
        public ?string $key = null,
    ) {}

    /**
     * What a module names a menu link by: its module and route ("forum:forum.index"), never its
     * text or address, which an admin can change. Links without both have no key.
     */
    public static function keyFor(?string $module, ?string $route): ?string
    {
        return $module !== null && $module !== '' && $route !== null && $route !== '' ? "{$module}:{$route}" : null;
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
            'key' => $this->key,
        ];
    }
}
