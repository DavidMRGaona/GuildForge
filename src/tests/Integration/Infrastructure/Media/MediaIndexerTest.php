<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Media\Jobs\RefreshMediaUsagesJob;
use App\Infrastructure\Media\Observers\RefreshMediaUsagesObserver;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\MediaIndexer;
use App\Infrastructure\Media\Services\MediaSourceSyncState;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\Support\Media\FakeMediaSource;
use Tests\Support\Media\InMemoryMediaCatalog;
use Tests\Support\Media\InMemoryMediaSource;
use Tests\TestCase;

final class MediaIndexerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private InMemoryMediaCatalog $catalog;

    private MediaSourceRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = new InMemoryMediaCatalog;
        $this->app->instance(MediaCatalogInterface::class, $this->catalog);
        $this->registry = $this->app->make(MediaSourceRegistryInterface::class);
        $this->registry->clear();
    }

    private function indexer(): MediaIndexerInterface
    {
        return $this->app->make(MediaIndexerInterface::class);
    }

    private function source(string $type, ?string $module = null): InMemoryMediaSource
    {
        $source = new InMemoryMediaSource($type, [$type.'/']);
        $alias = 'test.media.'.$type;
        $this->app->instance($alias, $source);
        /** @var class-string $alias */
        $this->registry->register($alias, $module);

        return $source;
    }

    private function registerAs(string $alias, object $source, ?string $module): void
    {
        $this->app->instance($alias, $source);
        /** @var class-string $alias */
        $this->registry->register($alias, $module);
    }

    private function file(string $id, int $bytes = 10): MediaFileModel
    {
        return MediaFileModel::query()->create([
            'file_id' => $id,
            'resource_type' => 'image',
            'folder' => substr($id, 0, (int) strrpos($id, '/') + 1),
            'format' => 'jpg',
            'bytes' => $bytes,
            'uploaded_at' => now()->subMonth(),
            'last_seen_at' => now()->subDay(),
        ]);
    }

    private function usage(string $fileId, string $type, string $recordId, string $role = 'Imagen', ?string $module = null): MediaUsageModel
    {
        return MediaUsageModel::query()->create([
            'file_id' => $fileId,
            'source_type' => $type,
            'module' => $module,
            'record_id' => $recordId,
            'role' => $role,
            'title' => 'Registro antiguo',
        ]);
    }

    /**
     * @return list<string> types the index is up to date with, sorted
     */
    private function upToDate(): array
    {
        $types = $this->app->make(MediaSourceSyncState::class)->upToDate($this->registry->sources());
        sort($types);

        return $types;
    }

    /**
     * @return array<string, string>
     */
    private function refreshFailures(): array
    {
        return $this->app->make(MediaSourceSyncState::class)->refreshFailures() ?? [];
    }

    /**
     * @return list<string>
     */
    private function fileIds(): array
    {
        /** @var list<string> */
        return MediaFileModel::query()->orderBy('file_id')->pluck('file_id')->all();
    }

    public function test_sync_adds_and_removes_files_from_a_complete_catalog(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 03:00:00', 'UTC'));
        $this->file('events/old');
        $this->file('events/kept', bytes: 10);
        $this->catalog->add('events/kept', bytes: 2048, uploadedAt: '2026-10-01 08:30:00');
        $this->catalog->add('events/new');

        $result = $this->indexer()->sync();

        $this->assertTrue($result->catalogComplete);
        $this->assertSame(2, $result->files);
        $this->assertSame(1, $result->added);
        $this->assertSame(1, $result->removed);
        $this->assertSame([], $result->failedSources);
        $this->assertSame(['events/kept', 'events/new'], $this->fileIds());

        $kept = MediaFileModel::query()->where('file_id', 'events/kept')->firstOrFail();
        $this->assertSame(2048, $kept->bytes);
        $this->assertSame('events/', $kept->folder);
        $this->assertSame('2026-10-01 08:30:00', $kept->uploaded_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-10 03:00:00', $kept->last_seen_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_incomplete_catalog_removes_nothing(): void
    {
        $this->file('events/a');
        $this->catalog->unavailable = true;
        $this->source('events')->use('events/b.jpg', 'e1');

        $result = $this->indexer()->sync();

        $this->assertFalse($result->catalogComplete);
        $this->assertSame(0, $result->added);
        $this->assertSame(0, $result->removed);
        $this->assertSame(['events/a'], $this->fileIds());
        // The usages are still brought up to date
        $this->assertTrue(MediaUsageModel::query()->where('file_id', 'events/b')->where('record_id', 'e1')->exists());
    }

    public function test_failing_source_keeps_its_rows(): void
    {
        // One row the stream still offers (with a new title) and one it no longer offers
        $this->usage('broken/kept', 'broken', 'r0');
        $this->usage('broken/old', 'broken', 'r1');
        $broken = $this->source('broken')->use('broken/kept.jpg', 'r0', title: 'Título nuevo');

        // More than one chunk is written before the source fails
        for ($i = 0; $i < 600; $i++) {
            $broken->use("broken/new{$i}.jpg", "n{$i}");
        }

        $broken->failing = true;
        $this->source('events')->use('events/a.jpg', 'e1');

        $result = $this->indexer()->sync();

        $this->assertSame(['broken'], $result->failedSources);
        $rows = MediaUsageModel::query()->where('source_type', 'broken')->orderBy('file_id')->get();
        $this->assertSame(['broken/kept', 'broken/old'], $rows->pluck('file_id')->all());
        $this->assertSame(['Registro antiguo', 'Registro antiguo'], $rows->pluck('title')->all());
        $this->assertTrue(MediaUsageModel::query()->where('source_type', 'events')->where('file_id', 'events/a')->exists());
    }

    public function test_rows_of_inactive_sources_are_untouched(): void
    {
        $this->usage('game-tables/x', 'gametables.tables', 't1', module: 'game-tables');
        $this->source('events');

        $this->indexer()->sync();

        $this->assertTrue(MediaUsageModel::query()
            ->where('source_type', 'gametables.tables')
            ->where('file_id', 'game-tables/x')
            ->where('title', 'Registro antiguo')
            ->exists());
    }

    public function test_sync_records_last_synced_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 03:15:00', 'UTC'));
        $this->assertNull($this->indexer()->lastSyncedAt());

        $this->indexer()->sync();

        $this->assertSame('2026-11-10T03:15:00+00:00', $this->app->make(SettingsServiceInterface::class)->get('media_library_last_synced_at'));
        $this->assertSame('2026-11-10T03:15:00+00:00', $this->indexer()->lastSyncedAt()?->format(DATE_ATOM));
        $this->assertNull($this->indexer()->lastSyncFailedAt());
    }

    public function test_a_sync_whose_catalog_fails_is_not_a_reconciliation(): void
    {
        $settings = $this->app->make(SettingsServiceInterface::class);
        $settings->set('media_library_last_synced_at', '2026-11-09T03:00:00+00:00');
        Carbon::setTestNow(Carbon::parse('2026-11-10 03:15:00', 'UTC'));
        $this->catalog->unavailable = true;

        $this->indexer()->sync();

        $this->assertSame('2026-11-09T03:00:00+00:00', $this->indexer()->lastSyncedAt()?->format(DATE_ATOM));
        $this->assertSame('2026-11-10T03:15:00+00:00', $settings->get('media_library_last_sync_failed_at'));
        $this->assertSame('2026-11-10T03:15:00+00:00', $this->indexer()->lastSyncFailedAt()?->format(DATE_ATOM));
    }

    public function test_a_sync_with_a_failing_source_is_not_a_reconciliation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 03:15:00', 'UTC'));
        $this->source('broken')->failing = true;

        $this->indexer()->sync();

        $this->assertNull($this->indexer()->lastSyncedAt());
        $this->assertSame('2026-11-10T03:15:00+00:00', $this->indexer()->lastSyncFailedAt()?->format(DATE_ATOM));
    }

    public function test_a_later_success_keeps_the_failure_but_is_newer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 03:15:00', 'UTC'));
        $this->catalog->unavailable = true;
        $this->indexer()->sync();

        Carbon::setTestNow(Carbon::parse('2026-11-10 04:00:00', 'UTC'));
        $this->catalog->unavailable = false;
        $this->indexer()->sync();

        $this->assertSame('2026-11-10T04:00:00+00:00', $this->indexer()->lastSyncedAt()?->format(DATE_ATOM));
        $this->assertSame('2026-11-10T03:15:00+00:00', $this->indexer()->lastSyncFailedAt()?->format(DATE_ATOM));
    }

    public function test_sync_records_which_sources_the_index_is_up_to_date_with(): void
    {
        // The library shows a source's images only while its rows come from a sync of the source as it is (core#837)
        $good = $this->source('good');
        $this->source('broken')->failing = true;
        $state = $this->app->make(MediaSourceSyncState::class);
        $state->record(['broken' => 'stale', 'gone' => 'kept'], []);

        $this->indexer()->sync();

        $this->assertSame(['gone' => 'kept', 'good' => $state->fingerprints([$good])['good']], $state->all());
    }

    public function test_a_source_whose_module_is_updated_while_it_syncs_is_not_taken_as_synced(): void
    {
        // Its rows may come from the old code: it stays concealed until the next sync
        ModuleModel::factory()->enabled()->create(['name' => 'shop', 'version' => '1.0.0']);
        $source = new class('shop.items', ['shop/']) extends FakeMediaSource
        {
            public function usages(): iterable
            {
                ModuleModel::query()->where('name', 'shop')->update(['version' => '1.1.0']);

                return [];
            }
        };
        $this->registerAs('test.media.shop', $source, 'shop');
        $state = $this->app->make(MediaSourceSyncState::class);
        $state->record(['shop.items' => 'old'], []);

        $this->indexer()->sync();

        $this->assertArrayNotHasKey('shop.items', $state->all());
    }

    public function test_sync_replaces_the_usages_of_each_active_source(): void
    {
        $this->usage('events/gone', 'events', 'e1');
        $this->usage('events/a', 'events', 'e2');
        $this->source('events', module: 'demo')
            ->use('events/a.jpg', 'e2', title: 'Evento nuevo')
            ->use('events/b.jpg', 'e3')
            // Not a file of this site: skipped, never stored
            ->use('https://example.com/elsewhere.jpg', 'e4');

        $this->indexer()->sync();

        $rows = MediaUsageModel::query()->where('source_type', 'events')->orderBy('record_id')->get();
        $this->assertSame(['events/a', 'events/b'], $rows->pluck('file_id')->all());
        $this->assertSame('Evento nuevo', $rows->firstOrFail()->title);
        $this->assertSame('demo', $rows->firstOrFail()->module);
    }

    public function test_sync_stores_a_repeated_usage_once(): void
    {
        $this->source('events')
            ->use('events/a.jpg', 'e1')
            ->use('events/a.png', 'e1');

        $this->indexer()->sync();

        $this->assertSame(1, MediaUsageModel::query()->where('source_type', 'events')->count());
    }

    public function test_sync_replaces_more_usages_than_one_chunk(): void
    {
        $source = $this->source('galleries');

        for ($i = 0; $i < 1203; $i++) {
            $source->use("galleries/p{$i}.jpg", "p{$i}");
        }

        $this->indexer()->sync();
        $this->assertSame(1203, MediaUsageModel::query()->where('source_type', 'galleries')->count());

        $source->usages = array_slice($source->usages, 0, 2);
        $this->indexer()->sync();
        $this->assertSame(2, MediaUsageModel::query()->where('source_type', 'galleries')->count());
    }

    public function test_sync_stores_whether_the_source_hides_each_usage(): void
    {
        $source = $this->source('badges')
            ->use('badges/secret.png', 'b1', title: 'Logro secreto', hidden: true)
            ->use('badges/public.png', 'b2', title: 'Primera partida');

        $this->indexer()->sync();

        $this->assertTrue(MediaUsageModel::query()->where('record_id', 'b1')->sole()->is_hidden);
        $this->assertFalse(MediaUsageModel::query()->where('record_id', 'b2')->sole()->is_hidden);

        // A secret that is revealed, and a usage hidden later, follow the source on the next sync
        $source->usages = [];
        $source->use('badges/secret.png', 'b1', title: 'Logro secreto')
            ->use('badges/public.png', 'b2', title: 'Primera partida', hidden: true);

        $this->indexer()->sync();

        $this->assertFalse(MediaUsageModel::query()->where('record_id', 'b1')->sole()->is_hidden);
        $this->assertTrue(MediaUsageModel::query()->where('record_id', 'b2')->sole()->is_hidden);
    }

    public function test_refreshing_one_record_stores_whether_its_usage_is_hidden(): void
    {
        $this->file('badges/secret');
        $this->usage('badges/secret', 'badges', 'b1');
        $this->source('badges')->use('badges/secret.png', 'b1', title: 'Logro secreto', hidden: true);

        $this->app->call([new RefreshMediaUsagesJob('badges', 'b1'), 'handle']);

        $row = MediaUsageModel::query()->where('source_type', 'badges')->sole();
        $this->assertTrue($row->is_hidden);
        $this->assertSame('Logro secreto', $row->title);
    }

    public function test_a_repeated_usage_is_hidden_when_any_copy_is(): void
    {
        // The same file, record and role twice: hidden wins, whichever comes last
        $source = $this->source('badges')
            ->use('badges/a.jpg', 'b1', hidden: true)
            ->use('badges/a.png', 'b1');

        $this->indexer()->sync();
        $this->assertTrue(MediaUsageModel::query()->where('source_type', 'badges')->sole()->is_hidden);

        $this->app->call([new RefreshMediaUsagesJob('badges', 'b1'), 'handle']);
        $this->assertTrue(MediaUsageModel::query()->where('source_type', 'badges')->sole()->is_hidden);

        $source->usages = array_reverse($source->usages);
        $this->indexer()->sync();
        $this->assertTrue(MediaUsageModel::query()->where('source_type', 'badges')->sole()->is_hidden);
    }

    public function test_a_repeated_usage_in_another_chunk_stays_hidden(): void
    {
        $source = $this->source('badges')->use('badges/a.jpg', 'b1', hidden: true);

        for ($i = 0; $i < 600; $i++) {
            $source->use("badges/p{$i}.jpg", "p{$i}");
        }

        $source->use('badges/a.png', 'b1');
        $this->indexer()->sync();

        $this->assertTrue(MediaUsageModel::query()->where('record_id', 'b1')->sole()->is_hidden);
    }

    public function test_usages_are_shown_unless_hidden(): void
    {
        $this->assertFalse($this->usage('events/a', 'events', 'e1')->refresh()->is_hidden);
    }

    public function test_a_refresh_whose_source_fails_leaves_its_type_not_up_to_date(): void
    {
        // A lost refresh may leave a usage shown that the source now hides (core#844)
        $events = $this->source('events');
        $this->source('articles');
        $this->indexer()->sync();
        $this->assertSame(['articles', 'events'], $this->upToDate());

        $events->failingForRecords = true;
        $this->app->call([new RefreshMediaUsagesJob('events', 'e1'), 'handle']);

        $this->assertSame(['articles'], $this->upToDate());
        $this->assertArrayHasKey('events', $this->refreshFailures());
    }

    public function test_a_refresh_without_a_source_in_this_worker_leaves_its_type_not_up_to_date(): void
    {
        // The source cannot be built here, or its module is enabled where the worker does not know it yet
        $this->source('events');
        $this->indexer()->sync();

        $this->app->call([new RefreshMediaUsagesJob('shop.items', 's1'), 'handle']);

        $this->assertSame(['shop.items'], array_keys($this->refreshFailures()));
        $this->assertSame(['events'], $this->upToDate());
    }

    public function test_a_refresh_that_cannot_be_queued_leaves_its_type_not_up_to_date(): void
    {
        $this->source('events');
        $this->source('articles');
        $this->indexer()->sync();
        $bus = Mockery::mock(Dispatcher::class);
        $bus->shouldReceive('dispatch')->andThrow(new RuntimeException('queue unreachable'));

        $this->app->make(MediaIndexer::class, ['bus' => $bus])->refresh('events', 'e1');

        $this->assertSame(['articles'], $this->upToDate());
    }

    public function test_a_refresh_that_cannot_be_queued_at_commit_time_leaves_its_type_not_up_to_date(): void
    {
        // The push waits for the caller's commit: refusing it then must neither escape nor go unrecorded
        $this->source('events');
        $this->source('articles');
        $this->indexer()->sync();
        $bus = Mockery::mock(Dispatcher::class);
        $bus->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('queue unreachable'));
        $indexer = $this->app->make(MediaIndexer::class, ['bus' => $bus]);

        DB::transaction(function () use ($indexer): void {
            $indexer->refresh('events', 'e1');
            // Nothing is queued before the commit
            $this->assertSame(['articles', 'events'], $this->upToDate());
        });

        $this->assertSame(['articles'], $this->upToDate());
    }

    public function test_a_refresh_asked_inside_a_transaction_that_rolls_back_is_never_queued(): void
    {
        $this->source('events');
        $this->indexer()->sync();
        $bus = Mockery::mock(Dispatcher::class);
        $bus->shouldNotReceive('dispatch');
        $indexer = $this->app->make(MediaIndexer::class, ['bus' => $bus]);

        try {
            DB::transaction(function () use ($indexer): void {
                $indexer->refresh('events', 'e1');

                throw new RuntimeException('save failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(['events'], $this->upToDate());
    }

    public function test_an_observer_that_cannot_work_out_the_records_leaves_its_type_not_up_to_date(): void
    {
        $this->source('events');
        $this->source('articles');
        $this->indexer()->sync();
        $observer = new RefreshMediaUsagesObserver('events', static fn (Model $model): array => throw new RuntimeException('no ids'));

        $observer->saved(new ModuleModel);

        $this->assertSame(['articles'], $this->upToDate());
    }

    public function test_a_refresh_job_that_never_finished_leaves_its_type_not_up_to_date(): void
    {
        // A timeout or a worker that died: handle() never got to record anything
        $this->source('events');
        $this->source('articles');
        $this->indexer()->sync();

        (new RefreshMediaUsagesJob('events', 'e1'))->failed(new RuntimeException('timed out'));

        $this->assertSame(['articles'], $this->upToDate());
    }

    public function test_a_refresh_that_works_does_not_bring_a_failed_type_back(): void
    {
        // A refresh does not know what else changed meanwhile: only a complete sync does
        $events = $this->source('events')->use('events/a.jpg', 'e1');
        $this->indexer()->sync();
        $events->failingForRecords = true;
        $this->app->call([new RefreshMediaUsagesJob('events', 'e1'), 'handle']);

        $events->failingForRecords = false;
        $this->app->call([new RefreshMediaUsagesJob('events', 'e1'), 'handle']);

        $this->assertSame([], $this->upToDate());

        $this->indexer()->sync();

        $this->assertSame(['events'], $this->upToDate());
        $this->assertSame([], $this->refreshFailures());
    }

    public function test_a_sync_that_fails_for_the_type_keeps_its_refresh_failure(): void
    {
        $events = $this->source('events');
        $this->source('articles');
        $this->indexer()->sync();
        $events->failingForRecords = true;
        $this->app->call([new RefreshMediaUsagesJob('events', 'e1'), 'handle']);
        $this->app->call([new RefreshMediaUsagesJob('articles', 'a1'), 'handle']);

        $events->failing = true;
        $this->indexer()->sync();

        $this->assertSame(['events'], array_keys($this->refreshFailures()));
        $this->assertSame(['articles'], $this->upToDate());
    }

    public function test_a_refresh_that_fails_while_a_sync_reads_the_source_keeps_the_type_failed(): void
    {
        // The sync may have read the record before it changed: the failure outlives it
        $source = new class('events', ['events/']) extends FakeMediaSource
        {
            public function usages(): iterable
            {
                resolve(MediaSourceSyncState::class)->markRefreshFailed('events');

                return [];
            }
        };
        $this->registerAs('test.media.events', $source, null);
        $this->app->make(MediaSourceSyncState::class)->markRefreshFailed('events');

        $this->indexer()->sync();

        $this->assertSame([], $this->upToDate());
        $this->assertArrayHasKey('events', $this->refreshFailures());
    }

    public function test_a_refresh_whose_files_cannot_be_looked_up_keeps_its_type_up_to_date(): void
    {
        // Its rows were written: only the catalog could not be asked about the files
        $this->source('events')->use('events/a.jpg', 'e1');
        $this->indexer()->sync();
        $this->catalog->unavailable = true;

        $this->app->call([new RefreshMediaUsagesJob('events', 'e1'), 'handle']);

        $this->assertSame(['events'], $this->upToDate());
        $this->assertSame([], $this->refreshFailures());
    }

    public function test_unreadable_refresh_failures_leave_no_type_up_to_date(): void
    {
        $this->source('events');
        $this->indexer()->sync();

        Schema::drop('media_source_refresh_failures');

        $this->assertSame([], $this->upToDate());
    }
}
