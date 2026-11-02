<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Services;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchSyncResultDTO;
use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Application\Search\Services\SearchIndexerInterface;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Infrastructure\Search\Index\PendingSearchRemovals;
use App\Infrastructure\Search\Index\SearchIndexMirror;
use App\Infrastructure\Search\Index\SearchIndexWriterInterface;
use App\Infrastructure\Search\Jobs\RefreshSearchDocumentJob;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The table is written first and is the source of truth; the engine mirrors the rows that changed
 * (see SearchIndexMirror for what happens when it is unreachable).
 *
 * A broken source never costs documents: when its documents() fails its rows stay as they were, and when a
 * registered source cannot even be loaded no type is treated as orphaned, except the rows of modules that
 * are not enabled.
 */
final readonly class SearchIndexer implements SearchIndexerInterface
{
    private const int CHUNK = 500;

    public function __construct(
        private SearchSourceRegistryInterface $registry,
        private SearchDocumentStoreInterface $store,
        private SearchIndexWriterInterface $writer,
        private PendingSearchRemovals $pending,
        private EnabledModulesResolverInterface $modules,
        private Dispatcher $bus,
    ) {}

    public function refresh(string $type, string $sourceId): void
    {
        $this->queue(new RefreshSearchDocumentJob($type, $sourceId));
    }

    public function forget(string $type, string $sourceId): void
    {
        $this->queue(new RefreshSearchDocumentJob($type, $sourceId, forget: true));
    }

    public function sync(?string $type = null): SearchSyncResultDTO
    {
        $indexed = 0;
        $removed = 0;
        /** @var list<string> $errors */
        $errors = [];
        $mirror = new SearchIndexMirror($this->writer, $this->pending);

        try {
            $mirror->syncSettings();
            $mirror->replayPendingRemovals();

            $enabled = $this->modules->names();
            $sources = $this->sourcesByType($enabled);
            $unresolved = $this->registry->unresolved();

            foreach ($sources as $sourceType => [$source, $module]) {
                if ($type !== null && $sourceType !== $type) {
                    continue;
                }

                [$written, $deleted] = $this->syncSource($sourceType, $source, $module, $mirror, $errors);
                $indexed += $written;
                $removed += $deleted;
            }

            // Types no source owns any more: a module disabled or uninstalled, a source removed
            $orphans = $type === null
                ? array_diff($this->store->types(), array_keys($sources))
                : (isset($sources[$type]) ? [] : [$type]);

            foreach ($unresolved as $sourceClass) {
                $errors[] = sprintf('Search source %s could not be loaded; orphan types kept.', $sourceClass);
            }

            foreach ($orphans as $orphan) {
                $removed += $this->removeOrphan($orphan, $unresolved === [], $enabled, $mirror, $errors);
            }
        } catch (Throwable $e) {
            Log::warning('[Search] The search documents could not be synced.', ['exception' => $e]);
            $errors[] = 'Search documents not synced: '.$e->getMessage();
        }

        $errors = [...$errors, ...$mirror->errors()];

        return new SearchSyncResultDTO($indexed, $removed, $errors === [] ? null : implode('; ', $errors));
    }

    /**
     * Indexed sources by type with their module; sources of a module no longer enabled are left out, so their
     * types are emptied like any other orphan.
     *
     * @param  list<string>  $enabled
     * @return array<string, array{IndexedSearchSourceInterface, ?string}>
     */
    private function sourcesByType(array $enabled): array
    {
        $sources = [];

        // The registry resolves every class on each call: ask it once per sync and once per type, never per document
        foreach ($this->registry->indexedSources() as $source) {
            $type = $source->type();
            $module = $this->registry->moduleOf($type);

            if ($module !== null && ! in_array($module, $enabled, true)) {
                continue;
            }

            $sources[$type] = [$source, $module];
        }

        return $sources;
    }

    /**
     * @param  list<string>  $errors
     * @return array{int, int} documents written and removed
     */
    private function syncSource(string $type, IndexedSearchSourceInterface $source, ?string $module, SearchIndexMirror $mirror, array &$errors): array
    {
        /** @var array<string, true> $keep */
        $keep = [];
        /** @var list<string> $pending */
        $pending = [];

        try {
            foreach ($source->documents() as $document) {
                $pending[] = $this->store->upsert($type, $module, $document);
                $keep[$document->id] = true;

                if (count($pending) >= self::CHUNK) {
                    $mirror->put($pending);
                    $pending = [];
                }
            }

            $mirror->put($pending);
            $pending = [];

            $removed = $this->store->deleteExcept($type, array_map(strval(...), array_keys($keep)));
            $mirror->remove($removed);

            return [count($keep), count($removed)];
        } catch (Throwable $e) {
            // What was already written is mirrored, and nothing is removed for a type that did not finish
            $mirror->put($pending);
            Log::warning('[Search] A search type could not be synced; its documents were left as they were.', [
                'type' => $type,
                'module' => $module,
                'exception' => $e,
            ]);
            $errors[] = sprintf('Type "%s" not synced: %s', $type, $e->getMessage());

            return [count($keep), 0];
        }
    }

    /**
     * Empties an orphan type, or, while some registered source could not be loaded (it may own the type),
     * only removes the rows of modules that are not enabled.
     *
     * @param  list<string>  $enabled
     * @param  list<string>  $errors
     */
    private function removeOrphan(string $type, bool $allSourcesLoaded, array $enabled, SearchIndexMirror $mirror, array &$errors): int
    {
        try {
            $ids = $allSourcesLoaded
                ? $this->store->deleteType($type)
                : $this->store->deleteTypeOfDisabledModules($type, $enabled);
            $mirror->remove($ids);

            return count($ids);
        } catch (Throwable $e) {
            Log::warning('[Search] The documents of a search type without source could not be removed.', ['type' => $type, 'exception' => $e]);
            $errors[] = sprintf('Type "%s" not removed: %s', $type, $e->getMessage());

            return 0;
        }
    }

    private function queue(RefreshSearchDocumentJob $job): void
    {
        try {
            $this->bus->dispatch($job->afterCommit());
        } catch (Throwable $e) {
            Log::warning('[Search] Could not queue a search document refresh.', [
                'type' => $job->type,
                'source_id' => $job->sourceId,
                'exception' => $e,
            ]);
        }
    }
}
