<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Knowledge;

use App\Application\Knowledge\DTOs\KnowledgeIndexResultDTO;
use App\Application\Knowledge\DTOs\KnowledgeSyncReportDTO;
use App\Application\Knowledge\Services\KnowledgeCatalogSynchronizerInterface;
use App\Application\Knowledge\Services\KnowledgeIndexerInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleUninstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Domain\Updates\Events\ModuleUpdateCompleted;
use App\Infrastructure\Knowledge\Catalog\KnowledgeSourceLocator;
use App\Infrastructure\Knowledge\Jobs\SyncKnowledgeCatalogJob;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\Knowledge\KnowledgeFixtures;
use Tests\TestCase;

final class SyncKnowledgeOnModuleChangeTest extends TestCase
{
    use KnowledgeFixtures;
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{object}>
     */
    public static function moduleEvents(): array
    {
        return [
            'enabled' => [new ModuleEnabled('id', 'sample')],
            'disabled' => [new ModuleDisabled('id', 'sample')],
            'updated' => [new ModuleUpdated('sample', '1.0.0', '1.1.0', '/tmp/sample')],
            'uninstalled' => [new ModuleUninstalled('sample', '1.0.0')],
            'update completed' => [new ModuleUpdateCompleted('sample', '1.0.0', '1.1.0')],
        ];
    }

    #[DataProvider('moduleEvents')]
    public function test_module_events_dispatch_a_sync(object $event): void
    {
        Queue::fake();

        event($event);

        Queue::assertPushed(SyncKnowledgeCatalogJob::class);
    }

    public function test_the_job_is_queued_and_unique(): void
    {
        $job = new SyncKnowledgeCatalogJob;

        $this->assertContains(ShouldQueue::class, class_implements($job));
        $this->assertContains(ShouldBeUniqueUntilProcessing::class, class_implements($job));
        $this->assertSame(300, $job->uniqueFor);
    }

    public function test_the_job_syncs_the_catalog_and_then_reindexes(): void
    {
        $order = [];
        $synchronizer = Mockery::mock(KnowledgeCatalogSynchronizerInterface::class);
        $synchronizer->shouldReceive('sync')->once()->andReturnUsing(function () use (&$order): KnowledgeSyncReportDTO {
            $order[] = 'sync';

            return new KnowledgeSyncReportDTO(0, 0, 0, []);
        });
        $indexer = Mockery::mock(KnowledgeIndexerInterface::class);
        $indexer->shouldReceive('reindex')->once()->andReturnUsing(function () use (&$order): KnowledgeIndexResultDTO {
            $order[] = 'reindex';

            return new KnowledgeIndexResultDTO(0, 0, null);
        });

        $modules = Mockery::mock(EnabledModulesResolverInterface::class);
        $modules->shouldReceive('reset')->once();

        (new SyncKnowledgeCatalogJob)->handle($synchronizer, $indexer, $modules);

        $this->assertSame(['sync', 'reindex'], $order);
    }

    public function test_the_job_sees_a_module_enabled_after_the_worker_booted(): void
    {
        $this->setUpKnowledgeSandbox();

        try {
            $this->writeModuleGuide('alpha', 'x.md', 'alpha.guide');
            $resolver = new class implements EnabledModulesResolverInterface
            {
                /** @var list<Module> */
                public array $enabled = [];

                /** @var list<Module>|null */
                private ?array $memo = null;

                public function names(): array
                {
                    return array_map(static fn (Module $module): string => $module->name()->value, $this->modules());
                }

                public function modules(): array
                {
                    return $this->memo ??= $this->enabled;
                }

                public function rejected(): array
                {
                    return [];
                }

                public function migrationPaths(): array
                {
                    return [];
                }

                public function reset(): void
                {
                    $this->memo = null;
                }
            };
            $this->app->instance(EnabledModulesResolverInterface::class, $resolver);
            $this->app->instance(KnowledgeSourceLocator::class, new KnowledgeSourceLocator($resolver, $this->corePath()));

            // The long-lived worker memoized the modules before the module was enabled
            $resolver->modules();
            $resolver->enabled = $this->knowledgeModules;

            $this->app->call([new SyncKnowledgeCatalogJob, 'handle']);

            $this->assertTrue(KnowledgeGuideModel::query()->where('guide_id', 'alpha.guide')->exists());
        } finally {
            $this->tearDownKnowledgeSandbox();
        }
    }

    #[DataProvider('moduleEvents')]
    public function test_a_failing_sync_does_not_break_the_module_event(object $event): void
    {
        config(['queue.default' => 'sync']);
        $synchronizer = Mockery::mock(KnowledgeCatalogSynchronizerInterface::class);
        $synchronizer->shouldReceive('sync')->andThrow(new RuntimeException('broken guide'));
        $this->app->instance(KnowledgeCatalogSynchronizerInterface::class, $synchronizer);
        Log::shouldReceive('warning')->once();

        event($event);

    }
}
