<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Application\Modules\Services\ModuleNavigationRegistryInterface;
use App\Application\Navigation\DTOs\MenuItemDTO;
use App\Application\Navigation\Services\MenuItemHrefResolverInterface;
use App\Application\Navigation\Services\MenuServiceInterface;
use App\Domain\Navigation\Entities\MenuItem;
use App\Domain\Navigation\Enums\LinkTarget;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Enums\MenuVisibility;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;
use App\Domain\Navigation\ValueObjects\MenuItemId;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

final readonly class MenuService implements MenuServiceInterface
{
    public function __construct(
        private MenuItemRepositoryInterface $repository,
        private AuthorizationServiceInterface $authService,
        private MenuItemHrefResolverInterface $hrefResolver,
        private ?ModuleNavigationRegistryInterface $moduleNavigationRegistry = null,
    ) {
    }

    public function getHeaderMenu(?Authenticatable $user): array
    {
        return $this->getMenuForLocation(MenuLocation::Header, $user);
    }

    public function getFooterMenu(?Authenticatable $user): array
    {
        return $this->getMenuForLocation(MenuLocation::Footer, $user);
    }

    public function userCanSeeItem(?Authenticatable $user, string $visibility, array $permissions): bool
    {
        $visibilityEnum = MenuVisibility::tryFrom($visibility) ?? MenuVisibility::Public;

        return match ($visibilityEnum) {
            MenuVisibility::Public => true,
            MenuVisibility::Authenticated => $user !== null,
            MenuVisibility::Guests => $user === null,
            MenuVisibility::Permission => $user !== null && $this->userHasAnyPermission($user, $permissions),
        };
    }

    public function syncModuleNavigation(?string $moduleName = null): void
    {
        if ($this->moduleNavigationRegistry === null) {
            return;
        }

        $moduleItems = $moduleName !== null
            ? $this->moduleNavigationRegistry->forModule($moduleName)
            : $this->moduleNavigationRegistry->all();

        DB::transaction(function () use ($moduleItems): void {
            foreach ($moduleItems as $navItem) {
                $this->syncModuleNavigationItem($navItem);
            }
        });
    }

    /**
     * @return array<MenuItemDTO>
     */
    private function getMenuForLocation(MenuLocation $location, ?Authenticatable $user): array
    {
        $menuItems = $this->repository->findActiveByLocationWithChildren($location);

        return $this->filterAndTransformItems($menuItems, $user);
    }

    /**
     * @param  array<MenuItem>  $items
     * @return array<MenuItemDTO>
     */
    private function filterAndTransformItems(array $items, ?Authenticatable $user): array
    {
        $result = [];

        foreach ($items as $item) {
            if (! $this->userCanSeeItem($user, $item->visibility()->value, $item->permissions())) {
                continue;
            }

            $children = $this->filterAndTransformItems($item->children(), $user);

            $result[] = new MenuItemDTO(
                id: $item->id()->value,
                label: $item->label(),
                href: $this->hrefResolver->resolve($item),
                target: $item->target()->value,
                icon: $item->icon(),
                children: $children,
                isActive: $item->isActive(),
            );
        }

        return $result;
    }

    /**
     * @param  array<string>  $permissions
     */
    private function userHasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        if ($permissions === []) {
            return true;
        }

        return $this->authService->canAny($user, $permissions);
    }

    private function syncModuleNavigationItem(NavigationItemDTO $navItem): void
    {
        // Check if item already exists in database (by module and route)
        $existingItems = $this->repository->findByLocation(MenuLocation::Header);

        foreach ($existingItems as $existing) {
            if ($existing->module() === $navItem->module && $existing->route() === $navItem->route) {
                // Item already exists, skip
                return;
            }
        }

        // Create new menu item from module navigation
        $menuItem = new MenuItem(
            id: MenuItemId::generate(),
            location: MenuLocation::Header,
            parentId: null,
            label: $navItem->label,
            url: null,
            route: $navItem->route,
            routeParams: [],
            icon: $navItem->icon,
            target: LinkTarget::Self,
            visibility: $navItem->permissions !== [] ? MenuVisibility::Permission : MenuVisibility::Public,
            permissions: $navItem->permissions,
            sortOrder: $navItem->sort !== 0 ? $navItem->sort : $this->repository->maxSortOrder(MenuLocation::Header) + 1,
            isActive: true,
            module: $navItem->module,
        );

        $this->repository->save($menuItem);
    }
}
