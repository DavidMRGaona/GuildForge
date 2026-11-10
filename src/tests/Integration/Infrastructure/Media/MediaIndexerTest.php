<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media;

use App\Application\Media\Services\MediaCatalogInterface;
use App\Application\Media\Services\MediaIndexerInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
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
}
