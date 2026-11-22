<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Storage;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\PathTraversalDetected;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Tests\TestCase;

final class PruneTemporaryUploadsCommandTest extends TestCase
{
    private FilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();

        // The disk Livewire uploads to while testing, emptied for each test
        $this->disk = Storage::fake(FileUploadConfiguration::disk());
    }

    public function test_it_deletes_uploads_and_their_metadata_older_than_six_hours(): void
    {
        $this->storeFile('livewire-tmp/old-upload.csv', minutesAgo: 361);
        $this->storeFile('livewire-tmp/old-upload.csv.json', minutesAgo: 361);
        $this->storeFile('livewire-tmp/orphan-metadata.xlsx.json', minutesAgo: 720);
        $this->storeFile('livewire-tmp/recent-upload.xlsx', minutesAgo: 359);
        $this->storeFile('livewire-tmp/recent-upload.xlsx.json', minutesAgo: 359);
        $this->storeFile('livewire-tmp/just-uploaded.csv', minutesAgo: 0);
        $this->storeFile('livewire-tmp/just-uploaded.csv.json', minutesAgo: 0);

        $this->artisanCommand('uploads:prune-temporary')
            ->expectsOutputToContain('Deleted 3 temporary uploads')
            ->assertSuccessful();

        $this->assertSame([
            'livewire-tmp/just-uploaded.csv',
            'livewire-tmp/just-uploaded.csv.json',
            'livewire-tmp/recent-upload.xlsx',
            'livewire-tmp/recent-upload.xlsx.json',
        ], $this->files());
    }

    public function test_nothing_outside_the_temporary_directory_is_touched(): void
    {
        $this->storeFile('livewire-tmp/old-upload.csv', minutesAgo: 600);
        $this->storeFile('livewire-tmp-archive/old-file.csv', minutesAgo: 600);
        $this->storeFile('neighbour/old-file.csv', minutesAgo: 600);
        $this->storeFile('neighbour/livewire-tmp/old-file.csv', minutesAgo: 600);
        $this->storeFile('old-file-at-the-root.csv', minutesAgo: 600);

        $this->artisanCommand('uploads:prune-temporary')->assertSuccessful();

        $this->assertSame([
            'livewire-tmp-archive/old-file.csv',
            'neighbour/livewire-tmp/old-file.csv',
            'neighbour/old-file.csv',
            'old-file-at-the-root.csv',
        ], $this->files());
    }

    public function test_it_follows_the_configured_temporary_directory(): void
    {
        config()->set('livewire.temporary_file_upload.directory', 'uploads/pending');
        $this->storeFile('uploads/pending/old-upload.csv', minutesAgo: 400);
        $this->storeFile('uploads/pending/old-upload.csv.json', minutesAgo: 400);
        $this->storeFile('livewire-tmp/old-upload.csv', minutesAgo: 400);

        $this->artisanCommand('uploads:prune-temporary')->assertSuccessful();

        $this->assertSame(['livewire-tmp/old-upload.csv'], $this->files());
    }

    public function test_a_directory_that_names_the_whole_disk_is_refused(): void
    {
        $log = Log::spy();
        config()->set('livewire.temporary_file_upload.directory', '/');
        $this->storeFile('livewire-tmp/old-upload.csv', minutesAgo: 400);
        $this->storeFile('old-file-at-the-root.csv', minutesAgo: 400);

        $this->artisanCommand('uploads:prune-temporary')->assertFailed();

        $this->assertSame(['livewire-tmp/old-upload.csv', 'old-file-at-the-root.csv'], $this->files());
        $log->shouldHaveReceived('error')->once();
    }

    public function test_a_directory_that_climbs_out_of_the_disk_is_refused(): void
    {
        $log = Log::spy();
        config()->set('livewire.temporary_file_upload.directory', '../elsewhere');
        $this->storeFile('livewire-tmp/old-upload.csv', minutesAgo: 400);

        $this->artisanCommand('uploads:prune-temporary')->assertFailed();

        $this->assertSame(['livewire-tmp/old-upload.csv'], $this->files());
        $log->shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context): bool => $context === ['exception' => PathTraversalDetected::class],
        );
    }

    public function test_it_runs_every_hour_without_overlapping(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(
            fn (Event $event): bool => str_contains($event->command ?? '', 'uploads:prune-temporary'),
        );

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('0 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        // A run that dies midway holds the lock 55 minutes, never the default 24 hours
        $this->assertSame(55, $event->expiresAt);
    }

    private function storeFile(string $path, int $minutesAgo): void
    {
        $this->disk->put($path, 'name,email');
        touch($this->disk->path($path), now()->subMinutes($minutesAgo)->getTimestamp());
        clearstatcache();
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $files = $this->disk->allFiles();
        sort($files);

        return $files;
    }
}
