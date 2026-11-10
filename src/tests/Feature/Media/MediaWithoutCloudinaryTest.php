<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Domain\Media\Exceptions\MediaCatalogUnavailableException;
use App\Infrastructure\Media\Jobs\RefreshMediaUsagesJob;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Without valid Cloudinary settings (local development, a fresh install, credential-free CI) the Cloudinary
 * client cannot be built: saving records must still work and the index must fail closed.
 */
final class MediaWithoutCloudinaryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Back to the real binding instead of the in-memory catalog every test gets
        $this->app->forgetInstance(MediaCatalogInterface::class);
        // A URL without credentials: the Cloudinary SDK refuses to build its client
        config(['filesystems.disks.images.url' => 'cloudinary://:@', 'cloudinary.cloud_url' => null]);
        // Models delete replaced images from the disk, which has its own client
        Storage::fake('images');
    }

    private function file(string $id): void
    {
        MediaFileModel::query()->create([
            'file_id' => $id,
            'resource_type' => 'image',
            'folder' => substr($id, 0, (int) strrpos($id, '/') + 1),
            'format' => 'jpg',
            'bytes' => 10,
            'uploaded_at' => now()->subMonth(),
            'last_seen_at' => now()->subDay(),
        ]);
    }

    public function test_the_real_catalog_binding_resolves_and_fails_only_when_used(): void
    {
        $catalog = $this->app->make(MediaCatalogInterface::class);

        $this->expectException(MediaCatalogUnavailableException::class);

        $catalog->all();
    }

    public function test_saving_an_event_and_a_user_still_works(): void
    {
        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/a.jpg']);
        $user = UserModel::factory()->create(['avatar_public_id' => 'avatars/u.jpg']);
        $user->update(['name' => 'Persona de ejemplo']);

        $this->assertTrue(MediaUsageModel::query()->where('source_type', 'events')->where('record_id', $event->id)->exists());
        $this->assertTrue(MediaUsageModel::query()->where('source_type', 'avatars')->where('record_id', $user->id)->exists());
    }

    public function test_the_job_keeps_files_when_the_catalog_cannot_be_built(): void
    {
        $log = Log::spy();
        $event = EventModel::factory()->create(['image_public_id' => null]);
        $this->file('events/2026/11/lost');
        MediaUsageModel::query()->create([
            'file_id' => 'events/2026/11/lost',
            'source_type' => 'events',
            'record_id' => $event->id,
            'role' => 'Imagen',
            'title' => 'Registro',
        ]);

        $this->app->call((new RefreshMediaUsagesJob('events', $event->id))->handle(...));

        $this->assertFalse(MediaUsageModel::query()->where('record_id', $event->id)->exists());
        $this->assertTrue(MediaFileModel::query()->where('file_id', 'events/2026/11/lost')->exists());
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_sync_without_a_catalog_keeps_every_file(): void
    {
        $this->file('events/2026/11/kept');

        $result = $this->app->make(MediaIndexerInterface::class)->sync();

        $this->assertFalse($result->catalogComplete);
        $this->assertTrue(MediaFileModel::query()->where('file_id', 'events/2026/11/kept')->exists());
    }

    public function test_an_indexer_that_cannot_be_built_never_breaks_a_save(): void
    {
        $log = Log::spy();
        $this->app->bind(MediaIndexerInterface::class, static fn (): never => throw new RuntimeException('cannot build'));

        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/a.jpg']);

        $this->assertTrue($event->exists);
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_a_save_with_nothing_to_refresh_does_not_build_the_indexer(): void
    {
        $this->app->bind(MediaIndexerInterface::class, static fn (): never => throw new RuntimeException('cannot build'));
        $log = Log::spy();

        UserModel::factory()->create(['avatar_public_id' => null]);

        $log->shouldNotHaveReceived('warning');
    }
}
