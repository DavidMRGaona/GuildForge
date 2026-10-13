<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Listeners;

use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;

/**
 * Reactivates the public menu links an admin created for the module. Nothing is
 * created here: a module's registerNavigation() describes the admin panel sidebar,
 * and its pages reach the public menu only through the menu editor.
 */
final readonly class ActivateMenuItemsOnModuleEnabled
{
    public function __construct(
        private MenuItemRepositoryInterface $menuItemRepository,
    ) {}

    public function handle(ModuleEnabled $event): void
    {
        $this->menuItemRepository->activateByModule($event->moduleName);
    }
}
