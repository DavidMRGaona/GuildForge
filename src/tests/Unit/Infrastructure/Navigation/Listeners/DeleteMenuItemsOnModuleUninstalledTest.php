<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Navigation\Listeners;

use App\Domain\Modules\Events\ModuleUninstalled;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;
use App\Infrastructure\Navigation\Listeners\DeleteMenuItemsOnModuleUninstalled;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeleteMenuItemsOnModuleUninstalledTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Test]
    public function it_deletes_menu_items_for_uninstalled_module(): void
    {
        $repository = Mockery::mock(MenuItemRepositoryInterface::class);
        $repository->shouldReceive('deleteByModule')
            ->once()
            ->with('blog');

        $listener = new DeleteMenuItemsOnModuleUninstalled($repository);
        $event = new ModuleUninstalled(
            moduleName: 'blog',
            moduleVersion: '1.0.0'
        );

        $listener->handle($event);
    }
}
