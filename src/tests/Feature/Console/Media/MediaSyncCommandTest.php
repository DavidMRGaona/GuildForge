<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Media;

use App\Application\Media\DTOs\MediaSyncResultDTO;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Infrastructure\Media\Jobs\SyncMediaLibraryJob;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Mockery;
use Tests\TestCase;

final class MediaSyncCommandTest extends TestCase
{
    private function indexerReturning(MediaSyncResultDTO $result): void
    {
        $indexer = Mockery::mock(MediaIndexerInterface::class);
        $indexer->shouldReceive('sync')->once()->andReturn($result);
        $this->app->instance(MediaIndexerInterface::class, $indexer);
    }

    public function test_command_prints_summary(): void
    {
        $this->indexerReturning(new MediaSyncResultDTO(catalogComplete: true, files: 12, added: 3, removed: 1, failedSources: []));

        $this->artisanCommand('media:sync')
            ->expectsOutputToContain('Files: 12')
            ->expectsOutputToContain('Added: 3')
            ->expectsOutputToContain('Removed: 1')
            ->assertSuccessful();
    }

    public function test_command_prints_summary_and_warns_on_incomplete_catalog(): void
    {
        $this->indexerReturning(new MediaSyncResultDTO(catalogComplete: false, files: 12, added: 0, removed: 0, failedSources: ['broken']));

        $this->artisanCommand('media:sync')
            ->expectsOutputToContain('Files: 12')
            ->expectsOutputToContain('The Cloudinary listing could not be read completely: no file was added or removed.')
            ->expectsOutputToContain('Usages not refreshed for: broken')
            ->assertFailed();
    }

    public function test_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($events->contains(
            fn (Event $event): bool => str_contains($event->command ?? '', 'media:sync')
                && $event->expression === '0 0 * * *'
                && $event->withoutOverlapping
        ));
    }

    public function test_the_sync_job_is_queued_unique_and_runs_the_sync(): void
    {
        $job = new SyncMediaLibraryJob;

        $this->assertContains(ShouldQueue::class, class_implements($job));
        $this->assertContains(ShouldBeUnique::class, class_implements($job));

        $this->indexerReturning(new MediaSyncResultDTO(true, 0, 0, 0, []));
        $this->app->call($job->handle(...));
    }
}
