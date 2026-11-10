<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Filament\Pages\Settings\AboutPageSettings;
use App\Filament\Pages\Settings\LegalPagesSettings;
use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Media\Jobs\RefreshMediaUsagesJob;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\TestCase;

final class RefreshMediaOnSaveTest extends TestCase
{
    use LazilyRefreshDatabase;

    private InMemoryMediaCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        // Models delete replaced images from the disk on save
        Storage::fake('images');
        $this->catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $this->catalog);
    }

    private function runJob(string $type, string $recordId): void
    {
        $this->app->call([new RefreshMediaUsagesJob($type, $recordId), 'handle']);
    }

    private function file(string $id): MediaFileModel
    {
        return MediaFileModel::query()->create([
            'file_id' => $id,
            'resource_type' => 'image',
            'folder' => substr($id, 0, (int) strrpos($id, '/') + 1),
            'format' => 'jpg',
            'bytes' => 10,
            'uploaded_at' => now()->subMonth(),
            'last_seen_at' => now()->subDay(),
        ]);
    }

    private function usage(string $fileId, string $type, string $recordId): MediaUsageModel
    {
        return MediaUsageModel::query()->create([
            'file_id' => $fileId,
            'source_type' => $type,
            'record_id' => $recordId,
            'role' => 'Imagen',
            'title' => 'Registro',
        ]);
    }

    public function test_saving_an_event_queues_a_refresh(): void
    {
        Bus::fake();

        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/a.jpg']);

        Bus::assertDispatched(RefreshMediaUsagesJob::class, fn (RefreshMediaUsagesJob $job): bool => $job->type === 'events'
            && $job->recordId === $event->id
            && $job->afterCommit === true);

        $event->delete();

        // One for its image and one for its text, on create and on delete
        Bus::assertDispatchedTimes(RefreshMediaUsagesJob::class, 4);
    }

    public function test_saving_a_photo_refreshes_the_photo_as_a_gallery_usage(): void
    {
        Bus::fake();

        $photo = PhotoModel::factory()->create();

        Bus::assertDispatched(RefreshMediaUsagesJob::class, fn (RefreshMediaUsagesJob $job): bool => $job->type === 'galleries'
            && $job->recordId === $photo->id);
    }

    public function test_changing_an_avatar_refreshes_avatars_and_other_user_saves_do_not(): void
    {
        Bus::fake();
        $user = UserModel::factory()->create(['avatar_public_id' => null]);
        Bus::assertNotDispatched(RefreshMediaUsagesJob::class);

        $user->forceFill(['remember_token' => 'token'])->save();
        Bus::assertNotDispatched(RefreshMediaUsagesJob::class);

        $user->update(['avatar_public_id' => 'avatars/a.jpg']);
        Bus::assertDispatched(RefreshMediaUsagesJob::class, fn (RefreshMediaUsagesJob $job): bool => $job->type === 'avatars'
            && $job->recordId === $user->id);
    }

    public function test_a_queue_that_fails_never_breaks_the_save(): void
    {
        $log = Log::spy();
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new RuntimeException('queue down'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $this->app->make(MediaIndexerInterface::class)->refresh('events', 'e1');

        $log->shouldHaveReceived('warning')->once();
    }

    public function test_job_replaces_usages_of_the_record(): void
    {
        Bus::fake();
        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/new.jpg', 'title' => 'Partida de rol']);
        $other = EventModel::factory()->create(['image_public_id' => 'events/2026/11/other.jpg']);
        $this->usage('events/2026/11/old', 'events', $event->id);
        $this->usage('events/2026/11/other', 'events', $other->id);

        $this->runJob('events', $event->id);

        $rows = MediaUsageModel::query()->where('source_type', 'events')->where('record_id', $event->id)->get();
        $this->assertSame(['events/2026/11/new'], $rows->pluck('file_id')->all());
        $this->assertSame('Partida de rol', $rows->firstOrFail()->title);
        $this->assertTrue(MediaUsageModel::query()->where('record_id', $other->id)->exists());
    }

    public function test_job_adds_unknown_used_file_from_catalog(): void
    {
        Bus::fake();
        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/a.jpg']);
        $this->catalog->add('events/2026/11/a', bytes: 4096);

        $this->runJob('events', $event->id);

        $file = MediaFileModel::query()->where('file_id', 'events/2026/11/a')->firstOrFail();
        $this->assertSame(4096, $file->bytes);
        $this->assertSame('events/2026/11/', $file->folder);
    }

    public function test_job_does_not_look_up_files_already_known(): void
    {
        Bus::fake();
        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/a.jpg']);
        $this->file('events/2026/11/a');

        $this->runJob('events', $event->id);

        $this->assertSame([], $this->catalog->lookups);
    }

    public function test_job_drops_file_that_lost_its_last_usage_and_no_longer_exists(): void
    {
        Bus::fake();
        $event = EventModel::factory()->create(['image_public_id' => null]);
        $this->file('events/2026/11/gone');
        $this->usage('events/2026/11/gone', 'events', $event->id);

        $this->runJob('events', $event->id);

        $this->assertFalse(MediaUsageModel::query()->where('record_id', $event->id)->exists());
        $this->assertFalse(MediaFileModel::query()->where('file_id', 'events/2026/11/gone')->exists());
    }

    public function test_job_keeps_file_that_lost_its_last_usage_but_still_exists(): void
    {
        Bus::fake();
        $event = EventModel::factory()->create(['image_public_id' => null]);
        $this->file('events/2026/11/kept');
        $this->catalog->add('events/2026/11/kept');
        $this->usage('events/2026/11/kept', 'events', $event->id);

        $this->runJob('events', $event->id);

        $this->assertTrue(MediaFileModel::query()->where('file_id', 'events/2026/11/kept')->exists());
    }

    public function test_job_keeps_file_still_used_by_another_record(): void
    {
        Bus::fake();
        $event = EventModel::factory()->create(['image_public_id' => null]);
        $this->file('events/2026/11/shared');
        $this->usage('events/2026/11/shared', 'events', $event->id);
        $this->usage('events/2026/11/shared', 'articles', 'a1');

        $this->runJob('events', $event->id);

        $this->assertTrue(MediaFileModel::query()->where('file_id', 'events/2026/11/shared')->exists());
        $this->assertSame([], $this->catalog->lookups);
    }

    public function test_job_keeps_the_file_when_the_catalog_is_unavailable(): void
    {
        Bus::fake();
        $log = Log::spy();
        $event = EventModel::factory()->create(['image_public_id' => null]);
        $this->file('events/2026/11/unknown');
        $this->usage('events/2026/11/unknown', 'events', $event->id);
        $this->catalog->unavailable = true;

        $this->runJob('events', $event->id);

        $this->assertFalse(MediaUsageModel::query()->where('record_id', $event->id)->exists());
        $this->assertTrue(MediaFileModel::query()->where('file_id', 'events/2026/11/unknown')->exists());
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_job_for_a_type_without_source_leaves_its_rows(): void
    {
        $this->usage('game-tables/x', 'gametables.tables', 't1');

        $this->runJob('gametables.tables', 't1');

        $this->assertTrue(MediaUsageModel::query()->where('source_type', 'gametables.tables')->exists());
    }

    public function test_saving_an_event_keeps_the_index_in_step(): void
    {
        // The test queue runs the job right away, after the save
        $event = EventModel::factory()->create(['image_public_id' => 'events/2026/11/first.jpg']);
        $this->assertSame(['events/2026/11/first'], MediaUsageModel::query()->where('record_id', $event->id)->pluck('file_id')->all());

        $event->update(['image_public_id' => 'events/2026/11/second.jpg']);
        $this->assertSame(['events/2026/11/second'], MediaUsageModel::query()->where('record_id', $event->id)->pluck('file_id')->all());

        $event->delete();
        $this->assertFalse(MediaUsageModel::query()->where('record_id', $event->id)->exists());
    }

    public function test_saving_site_settings_refreshes_image_keys(): void
    {
        $indexer = Mockery::spy(MediaIndexerInterface::class);
        $this->app->instance(MediaIndexerInterface::class, $indexer);
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm(['guild_name' => 'Gremio de ejemplo'])
            ->call('save');

        foreach (['site_logo_light', 'site_logo_dark', 'site_logo_email', 'site_favicon_light', 'site_favicon_dark'] as $key) {
            $indexer->shouldHaveReceived('refresh')->with('settings', $key)->once();
        }
        $indexer->shouldNotHaveReceived('refresh', ['settings', 'guild_name']);
    }

    public function test_saving_the_legal_pages_refreshes_their_texts(): void
    {
        $indexer = Mockery::spy(MediaIndexerInterface::class);
        $this->app->instance(MediaIndexerInterface::class, $indexer);
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(LegalPagesSettings::class)->call('save');

        foreach (['legal_privacy_content', 'legal_notice_content', 'legal_cookies_content', 'legal_terms_content'] as $key) {
            $indexer->shouldHaveReceived('refresh')->with('rich_text', 'settings:'.$key)->once();
        }
        $indexer->shouldNotHaveReceived('refresh', ['rich_text', 'settings:legal_entity_name']);
    }

    public function test_saving_the_about_page_refreshes_its_history(): void
    {
        $indexer = Mockery::spy(MediaIndexerInterface::class);
        $this->app->instance(MediaIndexerInterface::class, $indexer);
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(AboutPageSettings::class)
            ->fillForm([
                'location_name' => 'Local de ejemplo',
                'location_address' => 'Calle de ejemplo, 1',
                'location_lat' => '40.4',
                'location_lng' => '-3.7',
                'location_zoom' => '15',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $indexer->shouldHaveReceived('refresh')->with('rich_text', 'settings:about_history')->once();
        $indexer->shouldHaveReceived('refresh')->with('settings', 'about_hero_image')->once();
    }

    public function test_saving_an_article_refreshes_its_text(): void
    {
        Bus::fake();

        $article = ArticleModel::factory()->create();

        Bus::assertDispatched(RefreshMediaUsagesJob::class, fn (RefreshMediaUsagesJob $job): bool => $job->type === 'rich_text'
            && $job->recordId === 'articles:'.$article->id);
    }

    public function test_official_guides_written_by_the_catalog_sync_queue_nothing(): void
    {
        Bus::fake();

        $this->artisanCommand('knowledge:sync')->assertSuccessful()->run();

        Bus::assertNotDispatched(RefreshMediaUsagesJob::class, fn (RefreshMediaUsagesJob $job): bool => $job->type === 'rich_text');
    }
}
