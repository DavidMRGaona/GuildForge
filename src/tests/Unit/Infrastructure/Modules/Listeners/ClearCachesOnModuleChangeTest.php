<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Listeners;

use App\Application\Updates\Services\ModulePostUpdateRunnerInterface;
use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleInstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Infrastructure\Modules\Listeners\ClearCachesOnModuleChange;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClearCachesOnModuleChangeTest extends TestCase
{
    #[Test]
    #[DataProvider('moduleChanges')]
    public function it_is_noop_in_test_environment(ModuleEnabled|ModuleDisabled|ModuleInstalled|ModuleUpdated $event): void
    {
        $artisan = Artisan::spy();
        /** @var ModulePostUpdateRunnerInterface&MockInterface $runner */
        $runner = Mockery::mock(ModulePostUpdateRunnerInterface::class);
        $runner->shouldNotReceive('refreshCaches');

        // Returns early without touching caches
        (new ClearCachesOnModuleChange($runner))->handle($event);

        $artisan->shouldNotHaveReceived('call');
    }

    #[Test]
    #[DataProvider('moduleChanges')]
    public function it_refreshes_the_caches_in_a_fresh_process_outside_tests(ModuleEnabled|ModuleDisabled|ModuleInstalled|ModuleUpdated $event): void
    {
        $artisan = Artisan::spy();
        /** @var ModulePostUpdateRunnerInterface&MockInterface $runner */
        $runner = Mockery::mock(ModulePostUpdateRunnerInterface::class);
        $runner->shouldReceive('refreshCaches')->once();
        $this->app['env'] = 'production';

        try {
            (new ClearCachesOnModuleChange($runner))->handle($event);
        } finally {
            $this->app['env'] = 'testing';
        }

        // Rebuilding the config cache in this process would swap its application instance,
        // and clearing before rebuilding is the gap core#408 is about
        $artisan->shouldNotHaveReceived('call');
    }

    /**
     * @return array<string, array{ModuleEnabled|ModuleDisabled|ModuleInstalled|ModuleUpdated}>
     */
    public static function moduleChanges(): array
    {
        return [
            'enabled' => [new ModuleEnabled(moduleId: 'test-module-id', moduleName: 'announcements')],
            'disabled' => [new ModuleDisabled(moduleId: 'test-module-id', moduleName: 'announcements')],
            'installed' => [new ModuleInstalled(moduleName: 'announcements', moduleVersion: '1.0.0', modulePath: '/modules/announcements')],
            'updated' => [new ModuleUpdated(moduleName: 'announcements', previousVersion: '1.0.0', newVersion: '1.1.0', modulePath: '/modules/announcements')],
        ];
    }
}
