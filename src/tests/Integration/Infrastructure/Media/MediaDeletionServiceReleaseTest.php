<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaDeletionServiceInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Domain\Media\Enums\MediaDeletionKind;
use App\Domain\Media\Enums\MediaDeletionRefusal;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaDeletionModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Media\FakeMediaSource;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\Support\Media\UnresolvableMediaSource;
use Tests\TestCase;

/**
 * Releasing: what a record lets go of is deleted only when nobody uses it, checked live as the library does,
 * but without the index, the grace period, the library switch or the delete permission.
 */
final class MediaDeletionServiceReleaseTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private InMemoryMediaCatalog $catalog;

    private MediaSourceRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00:00');
        config(['media.delete_enabled' => false]);
        $this->app->instance(MediaFileIdResolverInterface::class, new CloudinaryMediaFileIdResolver('site'));
        $this->catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $this->catalog);
        $this->registry = $this->app->make(MediaSourceRegistryInterface::class);
        $this->registry->clear();
    }

    public function test_release_deletes_an_unindexed_unused_file_and_records_it_as_automatic(): void
    {
        $this->source('events');
        $this->catalog->add('events/2026/11/a');
        $actor = $this->actor();

        $refusal = $this->service()->releaseIfUnused(new MediaFileId('events/2026/11/a'), 'image', (string) $actor->getKey());

        $this->assertNull($refusal);
        $this->assertArrayNotHasKey('events/2026/11/a', $this->catalog->assets);
        $row = MediaDeletionModel::query()->sole();
        $this->assertSame(MediaDeletionKind::Automatic, $row->kind);
        $this->assertSame('deleted', $row->outcome);
        $this->assertSame((string) $actor->getKey(), $row->deleted_by);
        $this->assertSame('events/2026/11/', $row->folder);
        $this->assertSame('events', $row->source_type);
        $this->assertNull($row->bytes);
    }

    public function test_release_deletes_an_indexed_file_and_drops_it_from_the_index(): void
    {
        $this->source('events');
        // Uploaded yesterday: no grace period when a record lets go of it
        $this->file('events/a', uploadedAt: Carbon::now()->subDay());
        $this->usage('events/a', 'events');

        $this->assertNull($this->service()->releaseIfUnused(new MediaFileId('events/a'), 'image', null));

        $this->assertArrayNotHasKey('events/a', $this->catalog->assets);
        $this->assertFalse(MediaFileModel::query()->where('file_id', 'events/a')->exists());
        $this->assertFalse(MediaUsageModel::query()->where('file_id', 'events/a')->exists());
        $row = MediaDeletionModel::query()->sole();
        $this->assertSame(1000, $row->bytes);
        $this->assertNull($row->deleted_by);
    }

    public function test_release_keeps_a_file_used_by_another_source(): void
    {
        $this->source('events');
        $this->source('articles', usesFile: static fn (MediaFileId $file): bool => $file->value === 'events/a');
        $this->catalog->add('events/a');

        $this->assertKept('events/a', MediaDeletionRefusal::InUse);
    }

    public function test_release_keeps_a_file_when_a_source_throws(): void
    {
        $this->source('events');
        $this->source('articles', usesFile: static fn (): bool => throw new RuntimeException('database down'));
        $this->catalog->add('events/a');

        $this->assertKept('events/a', MediaDeletionRefusal::CheckFailed);
    }

    public function test_release_keeps_an_unclaimed_file(): void
    {
        $this->source('events');
        $this->catalog->add('legacy/a');

        $this->assertKept('legacy/a', MediaDeletionRefusal::Unclaimed);
    }

    public function test_release_keeps_a_file_in_a_hidden_folder(): void
    {
        $this->source('avatars', hidden: true);
        $this->catalog->add('avatars/a');

        $this->assertKept('avatars/a', MediaDeletionRefusal::Hidden);
    }

    public function test_release_keeps_a_file_with_usages_of_an_inactive_source(): void
    {
        $this->source('events');
        $this->file('events/a');
        $this->usage('events/a', 'gametables.tables', 'gametables');

        $this->assertKept('events/a', MediaDeletionRefusal::UsedByInactiveSource);
    }

    public function test_release_keeps_files_while_a_source_is_unresolved(): void
    {
        $this->source('events');
        $this->catalog->add('events/a');
        $this->registry->register(UnresolvableMediaSource::class);

        $this->assertKept('events/a', MediaDeletionRefusal::CheckFailed);
    }

    public function test_release_works_with_the_library_switch_off(): void
    {
        $this->source('events');
        $this->catalog->add('events/a');

        $this->assertFalse($this->service()->isEnabled());
        $this->assertNull($this->service()->releaseIfUnused(new MediaFileId('events/a'), 'image', null));
    }

    public function test_release_keeps_the_file_with_an_empty_prefix(): void
    {
        $this->app->instance(MediaFileIdResolverInterface::class, new CloudinaryMediaFileIdResolver(''));
        $this->source('events');
        $this->catalog->add('events/a');

        $this->assertKept('events/a', MediaDeletionRefusal::CheckFailed);
    }

    public function test_release_keeps_the_file_when_the_catalog_fails(): void
    {
        $this->source('events');
        $this->catalog->add('events/a');
        $this->catalog->unavailable = true;

        $this->assertSame(MediaDeletionRefusal::CatalogFailed, $this->service()->releaseIfUnused(new MediaFileId('events/a'), 'image', null));
        $this->assertSame(0, MediaDeletionModel::query()->count());
    }

    public function test_catalog_not_found_is_recorded_as_missing(): void
    {
        $this->source('events');

        $this->assertNull($this->service()->releaseIfUnused(new MediaFileId('events/a'), 'image', null));

        $this->assertSame('missing', MediaDeletionModel::query()->sole()->outcome);
    }

    public function test_manual_deletions_are_recorded_as_manual(): void
    {
        config(['media.delete_enabled' => true]);
        $this->source('events');
        $this->file('events/a');

        $this->service()->delete([new MediaFileId('events/a')], $this->editorWithPermissions(['media.view_any', 'media.delete']));

        $this->assertSame(MediaDeletionKind::Manual, MediaDeletionModel::query()->sole()->kind);
    }

    private function assertKept(string $fileId, MediaDeletionRefusal $reason): void
    {
        $this->assertSame($reason, $this->service()->releaseIfUnused(new MediaFileId($fileId), 'image', null));
        $this->assertArrayHasKey($fileId, $this->catalog->assets, 'A kept file must not reach the catalog');
        $this->assertSame(0, MediaDeletionModel::query()->count());
    }

    private function service(): MediaDeletionServiceInterface
    {
        return $this->app->make(MediaDeletionServiceInterface::class);
    }

    private function actor(): UserModel
    {
        return UserModel::factory()->create();
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
        $alias = 'test.media.release.'.$type;
        $this->app->instance($alias, $source);
        /** @var class-string $alias */
        $this->registry->register($alias);
    }

    private function file(string $id, ?Carbon $uploadedAt = null): void
    {
        $uploadedAt ??= Carbon::now()->subMonth();
        $this->catalog->add($id, 1000, $uploadedAt->toDateTimeString());

        MediaFileModel::query()->create([
            'file_id' => $id,
            'resource_type' => 'image',
            'folder' => substr($id, 0, (int) strrpos($id, '/') + 1),
            'format' => 'jpg',
            'bytes' => 1000,
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
}
