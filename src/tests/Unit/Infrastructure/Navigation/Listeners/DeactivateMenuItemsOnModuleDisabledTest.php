<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Navigation\Listeners;

use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;
use App\Infrastructure\Navigation\Listeners\DeactivateMenuItemsOnModuleDisabled;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeactivateMenuItemsOnModuleDisabledTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    #[Test]
    public function it_deactivates_menu_items_for_disabled_module(): void
    {
        $repository = Mockery::mock(MenuItemRepositoryInterface::class);
        $repository->shouldReceive('deactivateByModule')
            ->once()
            ->with('blog');

        $listener = new DeactivateMenuItemsOnModuleDisabled($repository);
        $event = new ModuleDisabled(
            moduleId: 'blog',
            moduleName: 'blog'
        );

        $listener->handle($event);
    }
}
