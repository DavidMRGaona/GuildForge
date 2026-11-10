<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media;

use App\Application\Media\DTOs\MediaDeletionResultDTO;
use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaDeletionServiceInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Domain\Media\Enums\MediaDeletionRefusal;
use App\Domain\Media\Exceptions\MediaDeletionDisabledException;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaDeletionModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Media\FakeMediaSource;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\TestCase;

final class MediaDeletionServiceTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private InMemoryMediaCatalog $catalog;

    private MediaSourceRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00:00');
        config(['media.delete_enabled' => true]);
        $this->app->instance(MediaFileIdResolverInterface::class, new CloudinaryMediaFileIdResolver('site'));
        $this->catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $this->catalog);
        $this->registry = $this->app->make(MediaSourceRegistryInterface::class);
        $this->registry->clear();
    }

    public function test_disabled_switch_throws(): void
    {
        config(['media.delete_enabled' => false]);
        $this->source('events');
        $this->file('events/a');

        $this->assertFalse($this->service()->isEnabled());

        try {
            $this->service()->delete([new MediaFileId('events/a')], $this->deleter());
            $this->fail('Deleting with the switch off must throw');
        } catch (MediaDeletionDisabledException) {
            // Expected
        }

        $this->assertArrayHasKey('events/a', $this->catalog->assets);
        $this->assertSame(0, MediaDeletionModel::query()->count());
    }

    public function test_empty_prefix_is_never_enabled(): void
    {
        $this->app->instance(MediaFileIdResolverInterface::class, new CloudinaryMediaFileIdResolver(''));
        $this->source('events');
        $this->file('events/a');

        $this->assertFalse($this->service()->isEnabled());

        $this->expectException(MediaDeletionDisabledException::class);
        $this->service()->delete([new MediaFileId('events/a')], $this->deleter());
    }

    public function test_switch_on_with_a_prefix_is_enabled(): void
    {
        $this->assertTrue($this->service()->isEnabled());
    }

    public function test_actor_without_permission_is_rejected(): void
    {
        $this->source('events');
        $this->file('events/a');

        try {
            $this->service()->delete([new MediaFileId('events/a')], $this->editorWithPermissions(['media.view_any']));
            $this->fail('Deleting without media.delete must throw');
        } catch (AuthorizationException) {
            // Expected
        }

        $this->assertArrayHasKey('events/a', $this->catalog->assets);
        $this->assertTrue(MediaFileModel::query()->where('file_id', 'events/a')->exists());
    }

    public function test_deletes_unused_file_and_writes_audit_row(): void
    {
        $this->source('events');
        $this->file('events/a', bytes: 2048);
        $actor = $this->deleter();

        $result = $this->service()->delete([new MediaFileId('events/a')], $actor);

        $this->assertSame(1, $result->deleted);
        $this->assertSame(0, $result->missing);
        $this->assertSame(2048, $result->freedBytes);
        $this->assertSame([], $result->refused);
        $this->assertArrayNotHasKey('events/a', $this->catalog->assets);
        $this->assertFalse(MediaFileModel::query()->where('file_id', 'events/a')->exists());

        $row = MediaDeletionModel::query()->sole();
        $this->assertSame('events/a', $row->file_id);
        $this->assertSame('image', $row->resource_type);
        $this->assertSame('events/', $row->folder);
        $this->assertSame('events', $row->source_type);
        $this->assertSame(2048, $row->bytes);
        $this->assertSame(800, $row->width);
        $this->assertSame(600, $row->height);
        $this->assertSame('deleted', $row->outcome);
        $this->assertSame((string) $actor->getKey(), $row->deleted_by);
        $this->assertTrue($row->deleted_at->equalTo(Carbon::now()));
    }

    public function test_missing_in_cloudinary_is_recorded_as_missing(): void
    {
        $this->source('events');
        $this->file('events/a', bytes: 2048);
        unset($this->catalog->assets['events/a']);

        $result = $this->service()->delete([new MediaFileId('events/a')], $this->deleter());

        $this->assertSame(0, $result->deleted);
        $this->assertSame(1, $result->missing);
        $this->assertSame(0, $result->freedBytes);
        $this->assertSame('missing', MediaDeletionModel::query()->sole()->outcome);
        $this->assertFalse(MediaFileModel::query()->where('file_id', 'events/a')->exists());
    }

    public function test_file_used_since_the_page_loaded_is_refused(): void
    {
        // The index has no usage, but a record of another active source started using it since
        $this->source('events');
        $this->source('articles', usesFile: static fn (MediaFileId $file): bool => $file->value === 'events/a');
        $this->file('events/a');

        $result = $this->service()->delete([new MediaFileId('events/a')], $this->deleter());

        $this->assertRefused($result, 'events/a', MediaDeletionRefusal::InUse);
    }

    public function test_source_failing_in_uses_file_refuses(): void
    {
        $this->source('events');
        $this->source('articles', usesFile: static fn (): bool => throw new RuntimeException('database down'));
        $this->file('events/a');

        $result = $this->service()->delete([new MediaFileId('events/a')], $this->deleter());

        $this->assertRefused($result, 'events/a', MediaDeletionRefusal::CheckFailed);
    }

    public function test_usage_of_inactive_source_refuses(): void
    {
        // A disabled module keeps its usage rows; no active source answers for them
        $this->source('events');
        $this->file('events/a');
        $this->usage('events/a', 'gametables.tables', 'gametables');

        $result = $this->service()->delete([new MediaFileId('events/a')], $this->deleter());

        $this->assertRefused($result, 'events/a', MediaDeletionRefusal::UsedByInactiveSource);
    }

    public function test_indexed_usage_of_an_active_source_refuses(): void
    {
        // The live check finds nothing, but the index still says it is used: the doubt keeps the file
        $this->source('events');
        $this->file('events/a');
        $this->usage('events/a', 'events');

        $result = $this->service()->delete([new MediaFileId('events/a')], $this->deleter());

        $this->assertRefused($result, 'events/a', MediaDeletionRefusal::InUse);
    }

    public function test_unclaimed_file_refuses(): void
    {
        $this->source('events');
        $this->file('legacy/a');

        $result = $this->service()->delete([new MediaFileId('legacy/a')], $this->deleter());

        $this->assertRefused($result, 'legacy/a', MediaDeletionRefusal::Unclaimed);
    }

    public function test_hidden_file_refuses(): void
    {
        $this->source('avatars', hidden: true);
        $this->file('avatars/a');

        $result = $this->service()->delete([new MediaFileId('avatars/a')], $this->deleter());

        $this->assertRefused($result, 'avatars/a', MediaDeletionRefusal::Hidden);
    }

    public function test_recent_file_refuses(): void
    {
        $this->source('events');
        $this->file('events/a', uploadedAt: Carbon::now()->subDays(6));

        $result = $this->service()->delete([new MediaFileId('events/a')], $this->deleter());

        $this->assertRefused($result, 'events/a', MediaDeletionRefusal::Recent);
    }

    public function test_grace_period_is_measured_from_the_clock_at_delete_time(): void
    {
        $this->source('events');
        $this->file('events/a', uploadedAt: Carbon::now()->subDays(6));

        $this->travel(2)->days();
        $result = $this->service()->delete([new MediaFileId('events/a')], $this->deleter());

        $this->assertSame(1, $result->deleted);
    }

    public function test_file_not_in_the_index_refuses(): void
    {
        $this->source('events');
        $this->catalog->add('events/a');

        $result = $this->service()->delete([new MediaFileId('events/a')], $this->deleter());

        $this->assertSame(['events/a' => MediaDeletionRefusal::NotIndexed], $result->refused);
        $this->assertArrayHasKey('events/a', $this->catalog->assets);
        $this->assertSame(0, MediaDeletionModel::query()->count());
    }

    public function test_catalog_failure_writes_nothing(): void
    {
        $this->source('events');
        $this->file('events/a');
        $this->catalog->unavailable = true;

        $result = $this->service()->delete([new MediaFileId('events/a')], $this->deleter());

        $this->assertSame(0, $result->deleted);
        $this->assertSame(0, $result->missing);
        $this->assertSame(['events/a' => MediaDeletionRefusal::CatalogFailed], $result->refused);
        $this->assertSame(0, MediaDeletionModel::query()->count());
        $this->assertTrue(MediaFileModel::query()->where('file_id', 'events/a')->exists());
    }

    public function test_catalog_that_cannot_be_built_refuses_without_breaking(): void
    {
        $this->source('events');
        $this->file('events/a');
        $this->app->bind(MediaCatalogInterface::class, static fn () => throw new RuntimeException('no client'));

        $result = $this->service()->delete([new MediaFileId('events/a')], $this->deleter());

        $this->assertSame(['events/a' => MediaDeletionRefusal::CatalogFailed], $result->refused);
        $this->assertSame(0, MediaDeletionModel::query()->count());
    }

    public function test_a_record_that_cannot_be_written_after_the_store_deleted_is_logged(): void
    {
        $this->source('events');
        $this->file('events/a');
        Schema::drop('media_deletions');
        $log = Log::spy();

        $result = $this->service()->delete([new MediaFileId('events/a')], $this->deleter());

        // The remote file is gone: the result says so, and the log keeps what the record would have held
        $this->assertSame(1, $result->deleted);
        $this->assertArrayNotHasKey('events/a', $this->catalog->assets);
        $log->shouldHaveReceived('error')->once()->withArgs(
            static fn (string $message, array $context): bool => $context['file_id'] === 'events/a' && $context['outcome'] === 'deleted',
        );
    }

    public function test_bulk_mixes_deleted_and_refused(): void
    {
        $this->source('events');
        $this->file('events/old', bytes: 500);
        $this->file('events/new', uploadedAt: Carbon::now()->subDay());
        $this->file('legacy/a');

        $result = $this->service()->delete([
            new MediaFileId('events/old'),
            new MediaFileId('events/new'),
            new MediaFileId('legacy/a'),
        ], $this->deleter());

        $this->assertSame(1, $result->deleted);
        $this->assertSame(500, $result->freedBytes);
        $this->assertSame([
            'events/new' => MediaDeletionRefusal::Recent,
            'legacy/a' => MediaDeletionRefusal::Unclaimed,
        ], $result->refused);
        $this->assertSame(['events/old'], MediaDeletionModel::query()->pluck('file_id')->all());
    }

    public function test_the_same_file_twice_is_deleted_once(): void
    {
        $this->source('events');
        $this->file('events/a');

        $result = $this->service()->delete([new MediaFileId('events/a'), new MediaFileId('events/a')], $this->deleter());

        $this->assertSame(1, $result->deleted);
        $this->assertSame(0, $result->missing);
        $this->assertSame([], $result->refused);
        $this->assertSame(1, MediaDeletionModel::query()->count());
    }

    private function service(): MediaDeletionServiceInterface
    {
        return $this->app->make(MediaDeletionServiceInterface::class);
    }

    private function deleter(): UserModel
    {
        return $this->editorWithPermissions(['media.view_any', 'media.delete']);
    }

    /**
     * @param  (callable(MediaFileId): bool)|null  $usesFile
     */
    private function source(string $type, bool $hidden = false, ?callable $usesFile = null): void
    {
        $source = new class($type, [$type.'/'], $hidden, $usesFile) extends FakeMediaSource
        {
            /** @var (callable(MediaFileId): bool)|null */
            private $usesFile;

            /**
             * @param  list<string>  $folders
             * @param  (callable(MediaFileId): bool)|null  $usesFile
             */
            public function __construct(string $type, array $folders, bool $hidden, ?callable $usesFile)
            {
                parent::__construct($type, $folders, $hidden);
                $this->usesFile = $usesFile;
            }

            public function usesFile(MediaFileId $file): bool
            {
                return $this->usesFile !== null && ($this->usesFile)($file);
            }
        };
        $alias = 'test.media.deletion.'.$type;
        $this->app->instance($alias, $source);
        /** @var class-string $alias */
        $this->registry->register($alias);
    }

    /**
     * An indexed file that the in-memory catalog also holds.
     */
    private function file(string $id, int $bytes = 1000, ?Carbon $uploadedAt = null): void
    {
        $uploadedAt ??= Carbon::now()->subMonth();
        $this->catalog->add($id, $bytes, $uploadedAt->toDateTimeString());

        MediaFileModel::query()->create([
            'file_id' => $id,
            'resource_type' => 'image',
            'folder' => substr($id, 0, (int) strrpos($id, '/') + 1),
            'format' => 'jpg',
            'bytes' => $bytes,
            'width' => 800,
            'height' => 600,
            'uploaded_at' => $uploadedAt,
            'last_seen_at' => Carbon::now()->subDay(),
        ]);
    }

    private function usage(string $fileId, string $type, ?string $module = null): void
    {
        MediaUsageModel::query()->create([
            'file_id' => $fileId,
            'source_type' => $type,
            'module' => $module,
            'record_id' => 'record-1',
            'role' => 'Imagen',
            'title' => 'Registro',
        ]);
    }

    private function assertRefused(MediaDeletionResultDTO $result, string $fileId, MediaDeletionRefusal $reason): void
    {
        $this->assertSame(0, $result->deleted);
        $this->assertSame(0, $result->missing);
        $this->assertSame(0, $result->freedBytes);
        $this->assertSame([$fileId => $reason], $result->refused);
        $this->assertArrayHasKey($fileId, $this->catalog->assets, 'A refused file must not reach the catalog');
        $this->assertSame(0, MediaDeletionModel::query()->count());
        $this->assertTrue(MediaFileModel::query()->where('file_id', $fileId)->exists());
    }
}
