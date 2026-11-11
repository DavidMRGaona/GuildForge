<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleUninstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Domain\Updates\Events\ModuleUpdateCompleted;
use App\Infrastructure\Media\Jobs\SyncMediaLibraryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A module brings, changes or takes away media sources: until the library syncs, its files would show as
 * unused although the module uses them (core#517).
 */
final class SyncMediaLibraryOnModuleChangeTest extends TestCase
{
    // Other listeners of the same events update the menu and the permissions
    use RefreshDatabase;

    /**
     * @return iterable<string, array{object}>
     */
    public static function moduleChanges(): iterable
    {
        yield 'enabled' => [new ModuleEnabled('id', 'example')];
        yield 'disabled' => [new ModuleDisabled('id', 'example')];
        yield 'updated' => [new ModuleUpdated('example', '1.0.0', '1.1.0', '/modules/example')];
        yield 'uninstalled' => [new ModuleUninstalled('example', '1.0.0')];
        yield 'update completed' => [new ModuleUpdateCompleted('example', '1.0.0', '1.1.0')];
    }

    #[DataProvider('moduleChanges')]
    public function test_a_module_change_queues_a_media_library_sync(object $event): void
    {
        Queue::fake();

        event($event);

        Queue::assertPushed(SyncMediaLibraryJob::class, 1);
    }
}
