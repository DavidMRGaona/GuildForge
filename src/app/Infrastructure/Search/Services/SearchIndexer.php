<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Services;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchSyncResultDTO;
use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Application\Search\Services\SearchIndexerInterface;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Infrastructure\Search\Index\SearchIndexWriterInterface;
use App\Infrastructure\Search\Jobs\RefreshSearchDocumentJob;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The table is written first and is the source of truth; the engine mirrors the rows that changed.
 *
 * A broken source never costs documents: its rows stay as they were until it works again. An unreachable
 * engine never stops the table from being rebuilt: after the first failure the engine is left alone for the
 * rest of the run (so one outage is one timeout, not one per chunk) and the next sync puts it right.
 */
final readonly class SearchIndexer implements SearchIndexerInterface
{
    private const int CHUNK = 500;

    public function __construct(
        private SearchSourceRegistryInterface $registry,
        private SearchDocumentStoreInterface $store,
        private SearchIndexWriterInterface $writer,
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
        $engineUp = true;
        $this->mirror(function (): void {
            $this->writer->syncSettings();
        }, $errors, $engineUp);

        try {
            $sources = $this->sourcesByType();

            foreach ($sources as $sourceType => [$source, $module]) {
                if ($type !== null && $sourceType !== $type) {
                    continue;
                }

                [$written, $deleted] = $this->syncSource($sourceType, $source, $module, $errors, $engineUp);
                $indexed += $written;
                $removed += $deleted;
            }

            // Types no source owns any more: a module disabled or uninstalled, a source removed
            $orphans = $type === null
                ? array_diff($this->store->types(), array_keys($sources))
                : (isset($sources[$type]) ? [] : [$type]);

            foreach ($orphans as $orphan) {
                $ids = $this->store->deleteType($orphan);
                $removed += count($ids);
                $this->remove($ids, $errors, $engineUp);
            }
        } catch (Throwable $e) {
            Log::warning('[Search] The search documents could not be synced.', ['exception' => $e]);
            $errors[] = 'Search documents not synced: '.$e->getMessage();
        }

        return new SearchSyncResultDTO($indexed, $removed, $errors === [] ? null : implode('; ', $errors));
    }

    /**
     * Indexed sources by type with their module; sources of a module no longer enabled are left out, so a
     * long-lived worker that booted with that module empties its types instead of rebuilding them.
     *
     * @return array<string, array{IndexedSearchSourceInterface, ?string}>
     */
    private function sourcesByType(): array
    {
        $enabled = $this->modules->names();
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
    private function syncSource(string $type, IndexedSearchSourceInterface $source, ?string $module, array &$errors, bool &$engineUp): array
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
                    $this->put($pending, $errors, $engineUp);
                    $pending = [];
                }
            }
        } catch (Throwable $e) {
            // What was already written is mirrored, and nothing is removed for a source that did not finish
            $this->put($pending, $errors, $engineUp);
            Log::warning('[Search] A search source failed; its documents were left as they were.', [
                'type' => $type,
                'module' => $module,
                'exception' => $e,
            ]);
            $errors[] = sprintf('Type "%s" not synced: %s', $type, $e->getMessage());

            return [count($keep), 0];
        }

        $this->put($pending, $errors, $engineUp);

        $removed = $this->store->deleteExcept($type, array_map(strval(...), array_keys($keep)));
        $this->remove($removed, $errors, $engineUp);

        return [count($keep), count($removed)];
    }

    /**
     * @param  list<string>  $rowIds
     * @param  list<string>  $errors
     */
    private function put(array $rowIds, array &$errors, bool &$engineUp): void
    {
        if ($rowIds !== []) {
            $this->mirror(function () use ($rowIds): void {
                $this->writer->put($rowIds);
            }, $errors, $engineUp);
        }
    }

    /**
     * @param  list<string>  $rowIds
     * @param  list<string>  $errors
     */
    private function remove(array $rowIds, array &$errors, bool &$engineUp): void
    {
        if ($rowIds !== []) {
            $this->mirror(function () use ($rowIds): void {
                $this->writer->remove($rowIds);
            }, $errors, $engineUp);
        }
    }

    /**
     * Runs a write to the engine unless an earlier one failed during this run.
     *
     * @param  callable(): void  $write
     * @param  list<string>  $errors
     */
    private function mirror(callable $write, array &$errors, bool &$engineUp): void
    {
        if (! $engineUp) {
            return;
        }

        try {
            $write();
        } catch (Throwable $e) {
            $engineUp = false;
            Log::warning('[Search] The search index could not be updated.', ['exception' => $e]);
            $errors[] = 'Search index not updated: '.$e->getMessage();
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
