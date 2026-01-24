<?php

declare(strict_types=1);

namespace App\Application\Modules\DTOs;

final readonly class NavigationItemDTO
{
    /**
     * @param  array<NavigationItemDTO>  $children  Child navigation items
     * @param  array<string>  $permissions  Required permissions to view this item
     */
    public function __construct(
        public string $label,
        public ?string $route = null,
        public ?string $url = null,
        public ?string $icon = null,
        public string $group = 'default',
        public int $sort = 0,
        public array $children = [],
        public array $permissions = [],
        public ?string $module = null,
        public ?string $badge = null,
        public ?string $badgeColor = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $children = [];
        if (isset($data['children']) && is_array($data['children'])) {
            $children = array_map(
                fn (array $child): self => self::fromArray($child),
                $data['children']
            );
        }

        return new self(
            label: $data['label'] ?? '',
            route: $data['route'] ?? null,
            url: $data['url'] ?? null,
            icon: $data['icon'] ?? null,
            group: $data['group'] ?? 'default',
            sort: $data['sort'] ?? 0,
            children: $children,
            permissions: $data['permissions'] ?? [],
            module: $data['module'] ?? null,
            badge: $data['badge'] ?? null,
            badgeColor: $data['badgeColor'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'route' => $this->route,
            'url' => $this->url,
            'icon' => $this->icon,
            'group' => $this->group,
            'sort' => $this->sort,
            'children' => array_map(fn (self $child): array => $child->toArray(), $this->children),
            'permissions' => $this->permissions,
            'module' => $this->module,
            'badge' => $this->badge,
            'badgeColor' => $this->badgeColor,
        ];
    }

    public function hasChildren(): bool
    {
        return count($this->children) > 0;
    }

    public function requiresPermission(): bool
    {
        return count($this->permissions) > 0;
    }
}
