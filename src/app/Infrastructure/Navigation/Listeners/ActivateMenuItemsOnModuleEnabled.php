<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Listeners;

use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;

final readonly class ActivateMenuItemsOnModuleEnabled
{
    public function __construct(
        private MenuItemRepositoryInterface $menuItemRepository,
    ) {
    }

    public function handle(ModuleEnabled $event): void
    {
        $this->menuItemRepository->activateByModule($event->moduleName);
    }
}
