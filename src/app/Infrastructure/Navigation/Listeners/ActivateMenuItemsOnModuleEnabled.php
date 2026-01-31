<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Listeners;

use App\Application\Navigation\Services\MenuServiceInterface;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;

final readonly class ActivateMenuItemsOnModuleEnabled
{
    public function __construct(
        private MenuItemRepositoryInterface $menuItemRepository,
        private MenuServiceInterface $menuService,
    ) {
    }

    public function handle(ModuleEnabled $event): void
    {
        // First sync any new navigation items from the module (only for this module)
        $this->menuService->syncModuleNavigation($event->moduleName);

        // Then activate existing items for this module
        $this->menuItemRepository->activateByModule($event->moduleName);
    }
}
