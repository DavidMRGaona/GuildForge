<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Listeners;

use App\Domain\Modules\Events\ModuleUninstalled;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;

final readonly class DeleteMenuItemsOnModuleUninstalled
{
    public function __construct(
        private MenuItemRepositoryInterface $menuItemRepository,
    ) {
    }

    public function handle(ModuleUninstalled $event): void
    {
        $this->menuItemRepository->deleteByModule($event->moduleName);
    }
}
