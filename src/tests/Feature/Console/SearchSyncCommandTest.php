<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Application\Search\DTOs\SearchSyncResultDTO;
use App\Application\Search\Services\SearchIndexerInterface;
use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleUninstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use App\Domain\Updates\Events\ModuleUpdateCompleted;
use App\Infrastructure\Search\Jobs\SyncSearchIndexJob;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use App\Infrastructure\Search\Services\ProcessSearchSyncRunner;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\Search\FakeSearchSyncRunner;
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

        $this->artisanCommand('search:sync', ['--type' => 'events'])->assertSuccessful();

        $this->assertSame(['events'], $this->syncedTypes);
    }

    public function test_an_unknown_type_fails_without_syncing(): void
    {
        $this->indexerReturning(new SearchSyncResultDTO(0, 0, null));

        $this->artisanCommand('search:sync', ['--type' => 'evnets'])
            // One line: the type asked for and the ones that exist
            ->expectsOutputToContain('Unknown search type "evnets". Known types: articles, events, galleries.')
            ->assertFailed();

        $this->assertSame([], $this->syncedTypes);
    }

    public function test_a_stored_type_without_source_still_syncs_so_its_orphans_go(): void
    {
        $this->indexerReturning(new SearchSyncResultDTO(0, 2, null));
        SearchDocumentModel::query()->create([
            'type' => 'removed-module.things',
            'source_id' => '1',
            'title' => 'Cosa de ejemplo',
            'body' => '',
            'url' => '/cosas/1',
            'audience' => 'public',
            'locale' => 'es',
        ]);

        $this->artisanCommand('search:sync', ['--type' => 'removed-module.things'])->assertSuccessful();

        $this->assertSame(['removed-module.things'], $this->syncedTypes);
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

    public function test_the_sync_job_hands_the_sync_to_the_runner(): void
    {
        $runner = new FakeSearchSyncRunner;

        (new SyncSearchIndexJob('event'))->handle($runner);
        (new SyncSearchIndexJob)->handle($runner);

        $this->assertSame(['event', null], $runner->runs);
    }

    public function test_the_runner_syncs_in_a_fresh_php_process(): void
    {
        // The process that enabled a module booted without its provider, so its sources are unknown here
        Process::fake();

        $this->assertTrue($this->app->make(ProcessSearchSyncRunner::class)->sync());
        $this->assertTrue($this->app->make(ProcessSearchSyncRunner::class)->sync('event'));

        Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
            && $process->command[1] === base_path('artisan')
            && in_array('search:sync', $process->command, true)
            && ! in_array('--type=event', $process->command, true)
            && $process->timeout === 300);
        Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
            && in_array('--type=event', $process->command, true));
    }

    public function test_the_runner_logs_a_failed_sync_and_never_throws(): void
    {
        $log = Log::spy();
        Process::fake(['*' => Process::result(output: 'Indexed: 0', errorOutput: 'Type "event" not synced: boom', exitCode: 1)]);

        $this->assertFalse($this->app->make(ProcessSearchSyncRunner::class)->sync());

        $log->shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => str_contains((string) ($context['error'] ?? ''), 'boom'),
        );
    }

    public function test_the_runner_survives_a_process_that_cannot_start(): void
    {
        $log = Log::spy();
        Process::fake(fn (): never => throw new RuntimeException('cannot fork'));

        $this->assertFalse($this->app->make(ProcessSearchSyncRunner::class)->sync());

        $log->shouldHaveReceived('warning')->once();
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
