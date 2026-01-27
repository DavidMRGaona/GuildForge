<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Persistence\Eloquent\Repositories;

use App\Domain\Navigation\Entities\MenuItem;
use App\Domain\Navigation\Enums\LinkTarget;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Enums\MenuVisibility;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;
use App\Domain\Navigation\ValueObjects\MenuItemId;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentMenuItemRepository implements MenuItemRepositoryInterface
{
    public function findById(MenuItemId $id): ?MenuItem
    {
        $model = MenuItemModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByLocation(MenuLocation $location): array
    {
        $query = MenuItemModel::query();
        $this->applyLocationFilter($query, $location);

        $models = $query->orderBy('sort_order')->get();

        return $models->map(fn (MenuItemModel $m): MenuItem => $this->toDomain($m))->all();
    }

    public function findRootsByLocation(MenuLocation $location): array
    {
        $query = MenuItemModel::query();
        $this->applyLocationFilter($query, $location);
        $this->applyRootsFilter($query);

        $models = $query->orderBy('sort_order')->get();

        return $models->map(fn (MenuItemModel $m): MenuItem => $this->toDomain($m))->all();
    }

    public function findChildren(MenuItemId $parentId): array
    {
        $models = MenuItemModel::query()
            ->where('parent_id', $parentId->value)
            ->orderBy('sort_order')
            ->get();

        return $models->map(fn (MenuItemModel $m): MenuItem => $this->toDomain($m))->all();
    }

    public function findActiveByLocationWithChildren(MenuLocation $location): array
    {
        $query = MenuItemModel::query();
        $this->applyLocationFilter($query, $location);
        $this->applyActiveFilter($query);
        $this->applyRootsFilter($query);

        $models = $query
            ->with(['children' => function ($childQuery): void {
                $childQuery->where('is_active', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return $models->map(fn (MenuItemModel $m): MenuItem => $this->toDomainWithChildren($m))->all();
    }

    public function all(): array
    {
        $models = MenuItemModel::query()->orderBy('sort_order')->get();

        return $models->map(fn (MenuItemModel $m): MenuItem => $this->toDomain($m))->all();
    }

    public function allActive(): array
    {
        $query = MenuItemModel::query();
        $this->applyActiveFilter($query);

        $models = $query->orderBy('sort_order')->get();

        return $models->map(fn (MenuItemModel $m): MenuItem => $this->toDomain($m))->all();
    }

    public function save(MenuItem $menuItem): void
    {
        MenuItemModel::query()->updateOrCreate(
            ['id' => $menuItem->id()->value],
            $this->toArray($menuItem),
        );
    }

    public function delete(MenuItem $menuItem): void
    {
        MenuItemModel::query()->where('id', $menuItem->id()->value)->delete();
    }

    public function deleteByModule(string $module): void
    {
        MenuItemModel::query()->where('module', $module)->delete();
    }

    public function maxSortOrder(MenuLocation $location, ?MenuItemId $parentId = null): int
    {
        $query = MenuItemModel::query();
        $this->applyLocationFilter($query, $location);

        if ($parentId !== null) {
            $query->where('parent_id', $parentId->value);
        } else {
            $this->applyRootsFilter($query);
        }

        return (int) $query->max('sort_order');
    }

    private function toDomain(MenuItemModel $model): MenuItem
    {
        return new MenuItem(
            id: new MenuItemId($model->id),
            location: $model->location,
            parentId: $model->parent_id !== null ? new MenuItemId($model->parent_id) : null,
            label: $model->label,
            url: $model->url,
            route: $model->route,
            routeParams: $model->route_params ?? [],
            icon: $model->icon,
            target: $model->target,
            visibility: $model->visibility,
            permissions: $model->permissions ?? [],
            sortOrder: $model->sort_order,
            isActive: $model->is_active,
            module: $model->module,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at !== null
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
        );
    }

    private function toDomainWithChildren(MenuItemModel $model): MenuItem
    {
        $children = $model->children->map(
            fn (MenuItemModel $child): MenuItem => $this->toDomain($child)
        )->all();

        return new MenuItem(
            id: new MenuItemId($model->id),
            location: $model->location,
            parentId: $model->parent_id !== null ? new MenuItemId($model->parent_id) : null,
            label: $model->label,
            url: $model->url,
            route: $model->route,
            routeParams: $model->route_params ?? [],
            icon: $model->icon,
            target: $model->target,
            visibility: $model->visibility,
            permissions: $model->permissions ?? [],
            sortOrder: $model->sort_order,
            isActive: $model->is_active,
            module: $model->module,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at !== null
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
            children: $children,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(MenuItem $menuItem): array
    {
        return [
            'id' => $menuItem->id()->value,
            'location' => $menuItem->location()->value,
            'parent_id' => $menuItem->parentId()?->value,
            'label' => $menuItem->label(),
            'url' => $menuItem->url(),
            'route' => $menuItem->route(),
            'route_params' => $menuItem->routeParams(),
            'icon' => $menuItem->icon(),
            'target' => $menuItem->target()->value,
            'visibility' => $menuItem->visibility()->value,
            'permissions' => $menuItem->permissions(),
            'sort_order' => $menuItem->sortOrder(),
            'is_active' => $menuItem->isActive(),
            'module' => $menuItem->module(),
        ];
    }

    /**
     * @param  Builder<MenuItemModel>  $query
     */
    private function applyRootsFilter(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<MenuItemModel>  $query
     */
    private function applyActiveFilter(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<MenuItemModel>  $query
     */
    private function applyLocationFilter(Builder $query, MenuLocation $location): void
    {
        $query->where('location', $location);
    }
}
