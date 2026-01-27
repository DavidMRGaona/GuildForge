<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Entities;

use App\Domain\Navigation\Enums\LinkTarget;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Enums\MenuVisibility;
use App\Domain\Navigation\ValueObjects\MenuItemId;
use DateTimeImmutable;

final readonly class MenuItem
{
    /**
     * @param  array<string, mixed>  $routeParams
     * @param  array<string>  $permissions
     * @param  array<MenuItem>  $children
     */
    public function __construct(
        private MenuItemId $id,
        private MenuLocation $location,
        private ?MenuItemId $parentId,
        private string $label,
        private ?string $url,
        private ?string $route,
        private array $routeParams,
        private ?string $icon,
        private LinkTarget $target,
        private MenuVisibility $visibility,
        private array $permissions,
        private int $sortOrder,
        private bool $isActive,
        private ?string $module,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
        private array $children = [],
    ) {
    }

    public function id(): MenuItemId
    {
        return $this->id;
    }

    public function location(): MenuLocation
    {
        return $this->location;
    }

    public function parentId(): ?MenuItemId
    {
        return $this->parentId;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    public function route(): ?string
    {
        return $this->route;
    }

    /**
     * @return array<string, mixed>
     */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function icon(): ?string
    {
        return $this->icon;
    }

    public function target(): LinkTarget
    {
        return $this->target;
    }

    public function visibility(): MenuVisibility
    {
        return $this->visibility;
    }

    /**
     * @return array<string>
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function module(): ?string
    {
        return $this->module;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return array<MenuItem>
     */
    public function children(): array
    {
        return $this->children;
    }

    public function hasChildren(): bool
    {
        return count($this->children) > 0;
    }

    public function isParent(): bool
    {
        return $this->parentId === null;
    }

    public function isChild(): bool
    {
        return $this->parentId !== null;
    }

    public function isFromModule(): bool
    {
        return $this->module !== null;
    }
}
