<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Listeners;

use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Infrastructure\Modules\Listeners\SyncPermissionsOnModuleChange;
use Illuminate\Support\Facades\Event;
use Tests\Support\Authorization\FakePermissionSyncRunner;
use Tests\TestCase;

final class SyncPermissionsOnModuleChangeTest extends TestCase
{
    public function test_it_listens_to_modules_being_enabled_and_updated(): void
    {
        Event::fake();

        Event::assertListening(ModuleEnabled::class, SyncPermissionsOnModuleChange::class);
        Event::assertListening(ModuleUpdated::class, SyncPermissionsOnModuleChange::class);
    }

    public function test_it_syncs_when_a_module_is_enabled(): void
    {
        $runner = new FakePermissionSyncRunner;

        (new SyncPermissionsOnModuleChange($runner))->handle(new ModuleEnabled('module-id', 'announcements'));

        $this->assertSame(1, $runner->runs);
    }

    public function test_it_syncs_when_a_module_is_updated(): void
    {
        $runner = new FakePermissionSyncRunner;

        (new SyncPermissionsOnModuleChange($runner))->handle(new ModuleUpdated('announcements', '1.0.0', '1.1.0', '/modules/announcements'));

        $this->assertSame(1, $runner->runs);
    }
}
