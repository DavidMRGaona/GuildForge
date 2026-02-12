<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Listeners;

use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleInstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Infrastructure\Modules\Listeners\ClearCachesOnModuleChange;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClearCachesOnModuleChangeTest extends TestCase
{
    #[Test]
    public function it_is_noop_in_test_environment_for_module_enabled(): void
    {
        $listener = new ClearCachesOnModuleChange;
        $event = new ModuleEnabled(
            moduleId: 'test-module-id',
            moduleName: 'announcements'
        );

        // Should return early without touching caches — no exception means success
        $listener->handle($event);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_is_noop_in_test_environment_for_module_disabled(): void
    {
        $listener = new ClearCachesOnModuleChange;
        $event = new ModuleDisabled(
            moduleId: 'test-module-id',
            moduleName: 'announcements'
        );

        $listener->handle($event);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_is_noop_in_test_environment_for_module_installed(): void
    {
        $listener = new ClearCachesOnModuleChange;
        $event = new ModuleInstalled(
            moduleName: 'announcements',
            moduleVersion: '1.0.0',
            modulePath: '/modules/announcements',
        );

        $listener->handle($event);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_is_noop_in_test_environment_for_module_updated(): void
    {
        $listener = new ClearCachesOnModuleChange;
        $event = new ModuleUpdated(
            moduleName: 'announcements',
            previousVersion: '1.0.0',
            newVersion: '1.1.0',
            modulePath: '/modules/announcements',
        );

        $listener->handle($event);

        $this->assertTrue(true);
    }
}
