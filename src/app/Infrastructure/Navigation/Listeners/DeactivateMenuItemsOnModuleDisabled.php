<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Listeners;

use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;

final readonly class DeactivateMenuItemsOnModuleDisabled
{
    public function __construct(
        private MenuItemRepositoryInterface $menuItemRepository,
    ) {
    }

    public function handle(ModuleDisabled $event): void
    {
        $this->menuItemRepository->deactivateByModule($event->moduleName);
    }
}
