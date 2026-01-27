<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Navigation\Listeners;

use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Navigation\Repositories\MenuItemRepositoryInterface;
use App\Infrastructure\Navigation\Listeners\ActivateMenuItemsOnModuleEnabled;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActivateMenuItemsOnModuleEnabledTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_activates_menu_items_for_enabled_module(): void
    {
        $repository = Mockery::mock(MenuItemRepositoryInterface::class);
        $repository->shouldReceive('activateByModule')
            ->once()
            ->with('blog');

        $listener = new ActivateMenuItemsOnModuleEnabled($repository);
        $event = new ModuleEnabled(
            moduleId: 'blog',
            moduleName: 'blog'
        );

        $listener->handle($event);
    }
}
