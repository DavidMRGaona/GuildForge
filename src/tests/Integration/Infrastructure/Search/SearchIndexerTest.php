<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Application\Search\Services\SearchIndexerInterface;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Infrastructure\Search\Index\NullSearchIndexWriter;
use App\Infrastructure\Search\Index\ScoutSearchIndexWriter;
use App\Infrastructure\Search\Index\SearchIndexWriterInterface;
use App\Infrastructure\Search\Jobs\RefreshSearchDocumentJob;
use App\Infrastructure\Search\Persistence\Eloquent\Models\SearchDocumentModel;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\Support\Search\InMemoryIndexedSearchSource;
use Tests\Support\Search\RecordingSearchIndexWriter;
use Tests\Support\Search\SecondInMemoryIndexedSearchSource;
use Tests\Support\Search\ThrowingTypeIndexedSearchSource;
use Tests\Support\Search\UnresolvableIndexedSearchSource;
use Tests\TestCase;

final class SearchIndexerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SearchSourceRegistryInterface $registry;

    private SearchDocumentStoreInterface $store;

    private RecordingSearchIndexWriter $writer;

    /** @var list<string> */
    private array $enabledModules = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->app->make(SearchSourceRegistryInterface::class);
        $this->registry->clear();
        $this->store = $this->app->make(SearchDocumentStoreInterface::class);
        $this->writer = new RecordingSearchIndexWriter;
        $this->app->instance(SearchIndexWriterInterface::class, $this->writer);

        $modules = Mockery::mock(EnabledModulesResolverInterface::class);
        $modules->shouldReceive('names')->andReturnUsing(fn (): array => $this->enabledModules);
        $modules->shouldReceive('reset');
        $this->app->instance(EnabledModulesResolverInterface::class, $modules);
    }

    private function indexer(): SearchIndexerInterface
    {
        return $this->app->make(SearchIndexerInterface::class);
    }

    private function document(string $id, string $title = 'Torneo de primavera'): SearchDocumentDTO
    {
        return new SearchDocumentDTO(id: $id, title: $title, body: 'Cuerpo', url: '/eventos/'.$id);
    }

    /**
     * @param  list<SearchDocumentDTO>  $documents
     */
    private function source(string $type, array $documents, ?string $module = null, ?string $failWith = null): InMemoryIndexedSearchSource
    {
        $source = new InMemoryIndexedSearchSource($type, $documents, $failWith);
        $this->app->instance(InMemoryIndexedSearchSource::class, $source);
        $this->registry->register(InMemoryIndexedSearchSource::class, $module);

        return $source;
    }

    /**
     * @param  list<SearchDocumentDTO>  $documents
     */
    private function secondSource(string $type, array $documents): SecondInMemoryIndexedSearchSource
    {
        $source = new SecondInMemoryIndexedSearchSource($type, $documents);
        $this->app->instance(SecondInMemoryIndexedSearchSource::class, $source);
        $this->registry->register(SecondInMemoryIndexedSearchSource::class);

        return $source;
    }

    /**
     * @return list<string>
     */
    private function sourceIds(string $type): array
    {
        return array_values(SearchDocumentModel::query()->where('type', $type)->orderBy('source_id')->pluck('source_id')->all());
    }

    private function rowId(string $type, string $sourceId): string
    {
        return SearchDocumentModel::query()->where('type', $type)->where('source_id', $sourceId)->firstOrFail()->id;
    }

    /**
     * @return list<string>
     */
    private function pendingRemovals(): array
    {
        return array_values(DB::table('search_pending_removals')->orderBy('id')->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all());
    }

    public function test_sync_upserts_documents_and_removes_missing_ones(): void
    {
        $gone = $this->store->upsert('event', null, $this->document('gone'));
        $this->store->upsert('event', null, $this->document('a', 'Título antiguo'));
        $this->source('event', [$this->document('a', 'Título nuevo'), $this->document('b')]);

        $result = $this->indexer()->sync();

        $this->assertNull($result->error);
        $this->assertSame(2, $result->indexed);
        $this->assertSame(1, $result->removed);
        $this->assertSame(['a', 'b'], $this->sourceIds('event'));
        $this->assertSame('Título nuevo', SearchDocumentModel::query()->findOrFail($this->rowId('event', 'a'))->title);
        $this->assertSame(1, $this->writer->settingsSynced);
        $this->assertEqualsCanonicalizing([$this->rowId('event', 'a'), $this->rowId('event', 'b')], $this->writer->put);
        $this->assertSame([$gone], $this->writer->removed);
    }

    public function test_sync_records_the_module_of_each_type(): void
    {
        $this->enabledModules = ['sample'];
        $this->source('game_table', [$this->document('a')], 'sample');

        $this->indexer()->sync();

        $this->assertSame('sample', SearchDocumentModel::query()->findOrFail($this->rowId('game_table', 'a'))->module);
    }

    public function test_sync_removes_types_without_a_registered_source(): void
    {
        $orphan = $this->store->upsert('game_table', 'sample', $this->document('a'));
        $this->source('event', [$this->document('e')]);

        $result = $this->indexer()->sync();

        $this->assertNull($result->error);
        $this->assertSame(1, $result->removed);
        $this->assertSame([], $this->sourceIds('game_table'));
        $this->assertSame(['e'], $this->sourceIds('event'));
        $this->assertSame([$orphan], $this->writer->removed);
    }

    public function test_sync_removes_the_documents_of_a_module_no_longer_enabled(): void
    {
        // A long-lived worker still has the sources of a module disabled since it booted
        $this->enabledModules = [];
        $orphan = $this->store->upsert('game_table', 'sample', $this->document('a'));
        $source = $this->source('game_table', [$this->document('a')], 'sample');

        $result = $this->indexer()->sync();

        $this->assertSame(0, $source->documentsCalls);
        $this->assertSame(1, $result->removed);
        $this->assertSame([], $this->sourceIds('game_table'));
        $this->assertSame([$orphan], $this->writer->removed);
    }

    public function test_sync_of_one_type_leaves_the_other_types_alone(): void
    {
        $this->store->upsert('orphan', null, $this->document('o'));
        $this->source('event', [$this->document('e')]);
        $article = $this->secondSource('article', [$this->document('x')]);

        $result = $this->indexer()->sync('event');

        $this->assertNull($result->error);
        $this->assertSame(1, $result->indexed);
        $this->assertSame(0, $article->documentsCalls);
        $this->assertSame(['o'], $this->sourceIds('orphan'));
        $this->assertSame([], $this->sourceIds('article'));
    }

    public function test_sync_of_a_type_without_source_empties_it(): void
    {
        $orphan = $this->store->upsert('orphan', null, $this->document('o'));

        $result = $this->indexer()->sync('orphan');

        $this->assertSame(1, $result->removed);
        $this->assertSame([$orphan], $this->writer->removed);
    }

    public function test_a_failing_source_is_reported_and_others_still_sync(): void
    {
        $this->store->upsert('event', null, $this->document('kept'));
        $this->source('event', [$this->document('a')], failWith: 'source exploded');
        $this->secondSource('article', [$this->document('x')]);

        $result = $this->indexer()->sync();

        $this->assertNotNull($result->error);
        $this->assertStringContainsString('event', $result->error);
        $this->assertStringContainsString('source exploded', $result->error);
        // Rows of the failing type are left alone: no document is removed because of a broken source
        $this->assertSame(['a', 'kept'], $this->sourceIds('event'));
        $this->assertSame(['x'], $this->sourceIds('article'));
        $this->assertSame([], $this->writer->removed);
    }

    public function test_sync_reports_an_unreachable_engine_and_still_fills_the_table(): void
    {
        $this->writer->failWith = 'Connection refused';
        $this->store->upsert('event', null, $this->document('gone'));
        $this->source('event', [$this->document('a')]);

        $result = $this->indexer()->sync();

        $this->assertNotNull($result->error);
        $this->assertStringContainsString('Connection refused', $result->error);
        $this->assertSame(['a'], $this->sourceIds('event'));
    }

    public function test_a_failed_engine_removal_is_replayed_by_the_next_sync(): void
    {
        $gone = $this->store->upsert('event', null, $this->document('gone'));
        $source = $this->source('event', [$this->document('a')]);
        $this->writer->failOn = 'remove';

        $first = $this->indexer()->sync();

        $this->assertNotNull($first->error);
        $this->assertSame(['a'], $this->sourceIds('event'));
        $this->assertSame([$gone], $this->pendingRemovals());

        $this->writer->failOn = null;
        $source->documents = [$this->document('a')];
        $second = $this->indexer()->sync();

        $this->assertNull($second->error);
        $this->assertSame([$gone], $this->writer->removed);
        $this->assertSame([], $this->pendingRemovals());
    }

    public function test_removals_skipped_after_an_engine_failure_are_recorded(): void
    {
        $orphan = $this->store->upsert('orphan', null, $this->document('o'));
        $gone = $this->store->upsert('event', null, $this->document('gone'));
        $this->source('event', []);
        // The settings fail first, so the run never calls the engine again
        $this->writer->failOn = 'syncSettings';

        $this->indexer()->sync();

        $this->assertSame([], $this->writer->removed);
        $this->assertEqualsCanonicalizing([$orphan, $gone], $this->pendingRemovals());
    }

    public function test_a_type_sync_replays_every_pending_removal(): void
    {
        DB::table('search_pending_removals')->insert(['id' => 'f3d1c6d0-0000-4000-8000-000000000001', 'created_at' => now()]);
        $this->source('event', []);

        $this->indexer()->sync('event');

        $this->assertSame(['f3d1c6d0-0000-4000-8000-000000000001'], $this->writer->removed);
        $this->assertSame([], $this->pendingRemovals());
    }

    public function test_the_refresh_job_replays_pending_removals_before_its_own(): void
    {
        DB::table('search_pending_removals')->insert(['id' => 'f3d1c6d0-0000-4000-8000-000000000001', 'created_at' => now()]);
        $row = $this->store->upsert('event', null, $this->document('a'));
        $this->source('event', []);

        $this->indexer()->refresh('event', 'a');

        $this->assertSame(['f3d1c6d0-0000-4000-8000-000000000001', $row], $this->writer->removed);
        $this->assertSame([], $this->pendingRemovals());
    }

    public function test_nothing_is_recorded_without_a_search_engine(): void
    {
        $this->app->instance(SearchIndexWriterInterface::class, new NullSearchIndexWriter);
        $this->store->upsert('event', null, $this->document('gone'));
        $this->store->upsert('orphan', null, $this->document('o'));
        $this->source('event', [$this->document('a')]);

        $result = $this->indexer()->sync();
        $this->indexer()->forget('event', 'a');

        $this->assertNull($result->error);
        $this->assertSame(2, $result->removed);
        $this->assertSame([], $this->pendingRemovals());
    }

    public function test_a_source_that_cannot_be_loaded_keeps_orphan_rows_and_reports_it(): void
    {
        $this->enabledModules = ['kept-module'];
        $unloaded = $this->store->upsert('game_table', 'kept-module', $this->document('g'));
        $core = $this->store->upsert('page', null, $this->document('p'));
        $disabled = $this->store->upsert('campaign', 'disabled-module', $this->document('c'));
        $this->registry->register(UnresolvableIndexedSearchSource::class, 'kept-module');
        $this->source('event', [$this->document('e')]);

        $result = $this->indexer()->sync();

        $this->assertNotNull($result->error);
        $this->assertStringContainsString(UnresolvableIndexedSearchSource::class, $result->error);
        $this->assertNotNull(SearchDocumentModel::query()->find($unloaded));
        $this->assertNotNull(SearchDocumentModel::query()->find($core));
        // Rows of a module that is not enabled are still removed in the same run
        $this->assertNull(SearchDocumentModel::query()->find($disabled));
        $this->assertSame([$disabled], $this->writer->removed);
        $this->assertSame(['e'], $this->sourceIds('event'));
    }

    public function test_a_type_sync_keeps_its_rows_when_a_source_cannot_be_loaded(): void
    {
        $row = $this->store->upsert('game_table', null, $this->document('g'));
        $this->registry->register(ThrowingTypeIndexedSearchSource::class);

        $result = $this->indexer()->sync('game_table');

        $this->assertNotNull($result->error);
        $this->assertNotNull(SearchDocumentModel::query()->find($row));
    }

    public function test_a_store_failure_in_one_type_does_not_stop_the_others(): void
    {
        $real = $this->store;
        $this->app->instance(SearchDocumentStoreInterface::class, new readonly class($real) implements SearchDocumentStoreInterface
        {
            public function __construct(private SearchDocumentStoreInterface $store) {}

            public function upsert(string $type, ?string $module, SearchDocumentDTO $doc): string
            {
                return $this->store->upsert($type, $module, $doc);
            }

            public function delete(string $type, string $sourceId): ?string
            {
                return $this->store->delete($type, $sourceId);
            }

            public function deleteExcept(string $type, array $keepSourceIds): array
            {
                if ($type === 'event') {
                    throw new RuntimeException('database hiccup');
                }

                return $this->store->deleteExcept($type, $keepSourceIds);
            }

            public function deleteType(string $type): array
            {
                return $this->store->deleteType($type);
            }

            public function deleteTypeOfDisabledModules(string $type, array $enabledModules): array
            {
                return $this->store->deleteTypeOfDisabledModules($type, $enabledModules);
            }

            public function types(): array
            {
                return $this->store->types();
            }
        });
        $this->store->upsert('article', null, $this->document('gone'));
        $this->source('event', [$this->document('e')]);
        $this->secondSource('article', [$this->document('x')]);

        $result = $this->indexer()->sync();

        $this->assertNotNull($result->error);
        $this->assertStringContainsString('Type "event" not synced: database hiccup', $result->error);
        $this->assertSame(['x'], $this->sourceIds('article'));
    }

    public function test_refresh_upserts_the_document(): void
    {
        $this->source('event', [$this->document('a')]);

        $this->indexer()->refresh('event', 'a');

        $this->assertSame(['a'], $this->sourceIds('event'));
        $this->assertSame([$this->rowId('event', 'a')], $this->writer->put);
    }

    public function test_refresh_removes_document_when_source_returns_null(): void
    {
        $row = $this->store->upsert('event', null, $this->document('a'));
        $this->source('event', []);

        $this->indexer()->refresh('event', 'a');

        $this->assertSame([], $this->sourceIds('event'));
        $this->assertSame([$row], $this->writer->removed);
    }

    public function test_refresh_of_an_unknown_type_forgets_the_document(): void
    {
        $row = $this->store->upsert('orphan', null, $this->document('a'));

        $this->indexer()->refresh('orphan', 'a');

        $this->assertSame([], $this->sourceIds('orphan'));
        $this->assertSame([$row], $this->writer->removed);
    }

    public function test_refresh_keeps_the_row_when_a_source_cannot_be_loaded(): void
    {
        $row = $this->store->upsert('game_table', null, $this->document('g'));
        $this->registry->register(UnresolvableIndexedSearchSource::class);

        $this->indexer()->refresh('game_table', 'g');

        $this->assertNotNull(SearchDocumentModel::query()->find($row));
        $this->assertSame([], $this->writer->removed);
    }

    public function test_forget_removes_the_document_whatever_the_source_says(): void
    {
        $row = $this->store->upsert('event', null, $this->document('a'));
        $this->source('event', [$this->document('a')]);

        $this->indexer()->forget('event', 'a');

        $this->assertSame([], $this->sourceIds('event'));
        $this->assertSame([$row], $this->writer->removed);
    }

    public function test_refresh_and_forget_are_queued_after_commit(): void
    {
        Queue::fake();

        $this->indexer()->refresh('event', 'a');
        $this->indexer()->forget('event', 'b');

        Queue::assertPushed(RefreshSearchDocumentJob::class, fn (RefreshSearchDocumentJob $job): bool => $job->type === 'event'
            && $job->sourceId === 'a'
            && ! $job->forget
            && $job->afterCommit === true);
        Queue::assertPushed(RefreshSearchDocumentJob::class, fn (RefreshSearchDocumentJob $job): bool => $job->sourceId === 'b'
            && $job->forget
            && $job->afterCommit === true);
    }

    public function test_refresh_runs_after_the_transaction_commits_and_never_after_a_rollback(): void
    {
        $this->source('event', [$this->document('a'), $this->document('b')]);

        DB::transaction(function (): void {
            $this->indexer()->refresh('event', 'a');
            $this->assertSame([], $this->sourceIds('event'));
        });

        try {
            DB::transaction(function (): void {
                $this->indexer()->refresh('event', 'b');

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(['a'], $this->sourceIds('event'));
    }

    public function test_writer_failure_does_not_throw_from_refresh(): void
    {
        $log = Log::spy();
        $this->writer->failWith = 'Connection refused';
        $this->source('event', [$this->document('a')]);

        $this->indexer()->refresh('event', 'a');
        $this->assertSame(['a'], $this->sourceIds('event'));

        $this->source('event', [$this->document('a', 'Título nuevo')]);
        $this->indexer()->refresh('event', 'a');
        $this->assertSame('Título nuevo', SearchDocumentModel::query()->findOrFail($this->rowId('event', 'a'))->title);

        $row = $this->rowId('event', 'a');
        $this->indexer()->forget('event', 'a');
        $this->assertSame([], $this->sourceIds('event'));
        $this->assertSame([$row], $this->pendingRemovals());

        $log->shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_a_failing_source_does_not_throw_from_refresh(): void
    {
        $log = Log::spy();
        $source = new class('event') extends InMemoryIndexedSearchSource
        {
            public function document(string $id): ?SearchDocumentDTO
            {
                throw new RuntimeException('source exploded');
            }
        };
        $this->app->instance($source::class, $source);
        $this->registry->register($source::class);
        $row = $this->store->upsert('event', null, $this->document('a'));

        $this->indexer()->refresh('event', 'a');

        // A broken source leaves the row alone: the nightly sync settles it
        $this->assertSame(['a'], $this->sourceIds('event'));
        $this->assertNotNull(SearchDocumentModel::query()->find($row));
        $log->shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_refresh_never_throws_when_the_job_cannot_be_queued(): void
    {
        $log = Log::spy();
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new RuntimeException('queue down'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $this->indexer()->refresh('event', 'a');
        $this->indexer()->forget('event', 'a');

        $log->shouldHaveReceived('warning')->twice();
    }

    public function test_the_writer_follows_the_scout_driver(): void
    {
        $this->app->forgetInstance(SearchIndexWriterInterface::class);

        config(['scout.driver' => 'null']);
        $this->assertSame(NullSearchIndexWriter::class, $this->resolvedClass(SearchIndexWriterInterface::class));

        config(['scout.driver' => 'meilisearch']);
        $this->assertSame(ScoutSearchIndexWriter::class, $this->resolvedClass(SearchIndexWriterInterface::class));
    }

    private function resolvedClass(string $abstract): string
    {
        $instance = $this->app->make($abstract);
        $this->assertIsObject($instance);

        return $instance::class;
    }
}
