<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Search\DTOs\SearchSyncResultDTO;
use App\Application\Search\Services\SearchIndexerInterface;
use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleUninstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Domain\Updates\Events\ModuleUpdateCompleted;
use App\Infrastructure\Search\Jobs\SyncSearchIndexJob;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class SearchSyncCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string|null> */
    private array $syncedTypes = [];

    private function indexerReturning(SearchSyncResultDTO $result): void
    {
        $indexer = Mockery::mock(SearchIndexerInterface::class);
        $indexer->shouldReceive('sync')->andReturnUsing(function (?string $type = null) use ($result): SearchSyncResultDTO {
            $this->syncedTypes[] = $type;

            return $result;
        });
        $this->app->instance(SearchIndexerInterface::class, $indexer);
    }

    public function test_sync_command_reports_counts(): void
    {
        $this->indexerReturning(new SearchSyncResultDTO(3, 1, null));

        $this->artisanCommand('search:sync')
            ->expectsOutputToContain('Indexed: 3')
            ->expectsOutputToContain('Removed: 1')
            ->assertSuccessful();

        $this->assertSame([null], $this->syncedTypes);
    }

    public function test_sync_command_passes_the_type(): void
    {
        $this->indexerReturning(new SearchSyncResultDTO(1, 0, null));

        $this->artisanCommand('search:sync', ['--type' => 'event'])->assertSuccessful();

        $this->assertSame(['event'], $this->syncedTypes);
    }

    public function test_sync_command_fails_on_error(): void
    {
        $this->indexerReturning(new SearchSyncResultDTO(2, 0, 'Source "event" failed: boom'));

        $this->artisanCommand('search:sync')
            ->expectsOutputToContain('Indexed: 2')
            ->expectsOutputToContain('Source "event" failed: boom')
            ->assertFailed();
    }

    public function test_sync_is_scheduled_daily_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($events->contains(
            fn (Event $event): bool => str_contains($event->command ?? '', 'search:sync')
                && $event->expression === '0 0 * * *'
                && $event->withoutOverlapping
        ));
    }

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

        Queue::assertPushed(SyncSearchIndexJob::class, fn (SyncSearchIndexJob $job): bool => $job->type === null);
    }

    public function test_the_sync_job_is_queued_and_unique_per_type(): void
    {
        $job = new SyncSearchIndexJob;

        $this->assertContains(ShouldQueue::class, class_implements($job));
        $this->assertContains(ShouldBeUniqueUntilProcessing::class, class_implements($job));
        $this->assertSame(300, $job->uniqueFor);
        $this->assertNotSame($job->uniqueId(), (new SyncSearchIndexJob('event'))->uniqueId());
    }

    public function test_the_sync_job_reads_the_enabled_modules_again_before_syncing(): void
    {
        $order = [];
        $modules = Mockery::mock(EnabledModulesResolverInterface::class);
        $modules->shouldReceive('reset')->once()->andReturnUsing(function () use (&$order): void {
            $order[] = 'reset';
        });
        $indexer = Mockery::mock(SearchIndexerInterface::class);
        $indexer->shouldReceive('sync')->once()->with('event')->andReturnUsing(function () use (&$order): SearchSyncResultDTO {
            $order[] = 'sync';

            return new SearchSyncResultDTO(0, 0, null);
        });

        (new SyncSearchIndexJob('event'))->handle($indexer, $modules);

        $this->assertSame(['reset', 'sync'], $order);
    }

    public function test_dispatch_safely_never_fails_the_caller(): void
    {
        $log = Log::spy();
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new RuntimeException('queue down'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        SyncSearchIndexJob::dispatchSafely();

        $log->shouldHaveReceived('warning')->once();
    }
}
