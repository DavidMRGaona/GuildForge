<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Storage\TemporaryUploads;

use App\Infrastructure\Storage\TemporaryUploads\TemporaryUploadPruner;
use Illuminate\Contracts\Filesystem\Filesystem;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToRetrieveMetadata;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Logging\RecordingLogger;

final class TemporaryUploadPrunerTest extends TestCase
{
    private const int CUTOFF = 1_000_000;

    private const int OLD = self::CUTOFF - 1;

    private const int NEW = self::CUTOFF + 1;

    private RecordingLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new RecordingLogger;
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_deletes_files_and_metadata_older_than_the_cutoff_and_keeps_newer_ones(): void
    {
        $disk = $this->disk([
            'livewire-tmp/old.csv' => self::OLD,
            'livewire-tmp/old.csv.json' => self::OLD,
            'livewire-tmp/new.xlsx' => self::NEW,
            'livewire-tmp/new.xlsx.json' => self::NEW,
            'livewire-tmp/edge.csv' => self::CUTOFF,
        ]);
        $disk->shouldReceive('delete')->once()->with('livewire-tmp/old.csv')->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with('livewire-tmp/old.csv.json')->andReturnTrue();

        $result = $this->pruner()->prune('local', $disk, 'livewire-tmp', self::CUTOFF);

        $this->assertTrue($result->listed);
        $this->assertSame(2, $result->deleted);
        $this->assertSame(0, $result->failed);
        $this->assertSame([], $this->logger->records);
    }

    public function test_paths_outside_the_directory_are_never_touched_even_when_listed(): void
    {
        // An adapter that answered more than the directory asked for must not widen the pruning
        $outside = [
            'livewire-tmp-archive/old.csv',
            'other/livewire-tmp/old.csv',
            'old.csv',
            'livewire-tmp/../old.csv',
            'livewire-tmp/nested/../../old.csv',
            'livewire-tmp/./old.csv',
        ];
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('allFiles')->once()->with('livewire-tmp')->andReturn([...$outside, 'livewire-tmp/old.csv']);
        $disk->shouldReceive('lastModified')->once()->with('livewire-tmp/old.csv')->andReturn(self::OLD);
        $disk->shouldReceive('delete')->once()->with('livewire-tmp/old.csv')->andReturnTrue();

        foreach ($outside as $path) {
            $disk->shouldReceive('lastModified')->never()->with($path);
            $disk->shouldReceive('exists')->never()->with($path);
            $disk->shouldReceive('delete')->never()->with($path);
        }

        $result = $this->pruner()->prune('local', $disk, 'livewire-tmp', self::CUTOFF);

        $this->assertSame(1, $result->deleted);
        $this->assertSame(0, $result->failed);
        $this->assertSame([], $this->logger->records);
    }

    public function test_an_empty_directory_is_refused_without_listing_the_disk(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldNotReceive('allFiles');
        $disk->shouldNotReceive('delete');

        $result = $this->pruner()->prune('local', $disk, '', self::CUTOFF);

        $this->assertFalse($result->listed);
        $this->assertSame(0, $result->deleted);
        $this->assertCount(1, $this->logger->records);
        $this->assertSame('error', $this->logger->records[0]['level']);
        $this->assertSame(['disk' => 'local'], $this->logger->records[0]['context']);
    }

    public function test_a_listing_that_fails_is_logged_and_deletes_nothing(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('allFiles')->once()->andThrow(
            UnableToListContents::atLocation('livewire-tmp/secret-name.csv', true, new RuntimeException('secret-name')),
        );
        $disk->shouldNotReceive('delete');

        $result = $this->pruner()->prune('s3', $disk, 'livewire-tmp', self::CUTOFF);

        $this->assertFalse($result->listed);
        $this->assertCount(1, $this->logger->records);
        $this->assertSame('error', $this->logger->records[0]['level']);
        $this->assertSame(['disk' => 's3', 'exception' => UnableToListContents::class], $this->logger->records[0]['context']);
        $this->assertLogsNeverName('secret-name');
    }

    public function test_a_failing_file_is_counted_and_logged_without_its_name_and_the_rest_goes_on(): void
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('allFiles')->with('livewire-tmp')->andReturn([
            'livewire-tmp/secret-name.csv',
            'livewire-tmp/secret-other.csv',
            'livewire-tmp/secret-third.csv',
            'livewire-tmp/later.csv',
        ]);
        $disk->shouldReceive('lastModified')->with('livewire-tmp/secret-name.csv')
            ->andThrow(UnableToRetrieveMetadata::lastModified('livewire-tmp/secret-name.csv', 'secret-name'));
        $disk->shouldReceive('exists')->with('livewire-tmp/secret-name.csv')->andReturnTrue();
        $disk->shouldReceive('lastModified')->with('livewire-tmp/secret-other.csv')->andReturn(self::OLD);
        $disk->shouldReceive('delete')->with('livewire-tmp/secret-other.csv')->andReturnFalse();
        $disk->shouldReceive('lastModified')->with('livewire-tmp/secret-third.csv')->andReturn(self::OLD);
        $disk->shouldReceive('delete')->with('livewire-tmp/secret-third.csv')
            ->andThrow(UnableToDeleteFile::atLocation('livewire-tmp/secret-third.csv', 'secret-third'));
        $disk->shouldReceive('lastModified')->with('livewire-tmp/later.csv')->andReturn(self::OLD);
        $disk->shouldReceive('delete')->once()->with('livewire-tmp/later.csv')->andReturnTrue();

        $result = $this->pruner()->prune('s3', $disk, 'livewire-tmp', self::CUTOFF);

        $this->assertTrue($result->listed);
        $this->assertSame(1, $result->deleted);
        $this->assertSame(3, $result->failed);
        $this->assertCount(1, $this->logger->records);
        $this->assertSame('warning', $this->logger->records[0]['level']);
        $this->assertSame([
            'disk' => 's3',
            'failed' => 3,
            'exceptions' => [UnableToRetrieveMetadata::class, UnableToDeleteFile::class],
        ], $this->logger->records[0]['context']);
        $this->assertLogsNeverName('secret');
    }

    public function test_a_file_removed_meanwhile_is_not_a_failure(): void
    {
        // Another run, or the module that used it, deleted it between the listing and the check
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('allFiles')->andReturn(['livewire-tmp/gone.csv']);
        $disk->shouldReceive('lastModified')->andThrow(UnableToRetrieveMetadata::lastModified('livewire-tmp/gone.csv'));
        $disk->shouldReceive('exists')->andReturnFalse();
        $disk->shouldNotReceive('delete');

        $result = $this->pruner()->prune('local', $disk, 'livewire-tmp', self::CUTOFF);

        $this->assertSame(0, $result->deleted);
        $this->assertSame(0, $result->failed);
        $this->assertSame([], $this->logger->records);
    }

    /**
     * @param  array<string, int>  $files  path => last modified
     */
    private function disk(array $files): Filesystem&MockInterface
    {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('allFiles')->once()->with('livewire-tmp')->andReturn(array_keys($files));

        foreach ($files as $path => $lastModified) {
            $disk->shouldReceive('lastModified')->with($path)->andReturn($lastModified);
        }

        return $disk;
    }

    private function pruner(): TemporaryUploadPruner
    {
        return new TemporaryUploadPruner($this->logger);
    }

    private function assertLogsNeverName(string $needle): void
    {
        $this->assertStringNotContainsString($needle, (string) json_encode($this->logger->records));
    }
}
