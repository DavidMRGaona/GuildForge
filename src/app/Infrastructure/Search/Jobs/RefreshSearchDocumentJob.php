<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Jobs;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Application\Search\Services\SearchSourceRegistryInterface;
use App\Application\Search\Services\SearchSyncRunnerInterface;
use App\Infrastructure\Search\Index\PendingSearchRemovals;
use App\Infrastructure\Search\Index\SearchIndexMirror;
use App\Infrastructure\Search\Index\SearchIndexWriterInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Brings one stored document in line with its source: written when the source offers it, removed when it
 * does not (or when the record is forgotten, or no source owns the type any more). When no source here owns
 * the type and either some registered source could not be loaded or the row belongs to an enabled module (this
 * worker may have booted before the module was enabled), the type is synced by a fresh process, which loads
 * every enabled module, and the row is removed if that sync fails: a hidden record must never stay searchable.
 * That sync runs synchronously inside this job, and only a stale or broken worker ever gets there.
 *
 * A failing source or engine is logged and the job ends: with the sync queue it runs at commit time, where
 * an exception would reach the request. A source failure leaves the row as it was and the nightly sync
 * settles it; an engine failure is handled by SearchIndexMirror (puts are redone by every sync, removals are
 * recorded and replayed).
 */
final class RefreshSearchDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly string $type,
        public readonly string $sourceId,
        public readonly bool $forget = false,
    ) {}

    public function handle(
        SearchSourceRegistryInterface $registry,
        SearchDocumentStoreInterface $store,
        SearchIndexWriterInterface $writer,
        PendingSearchRemovals $pending,
        EnabledModulesResolverInterface $modules,
        SearchSyncRunnerInterface $runner,
    ): void {
        $mirror = new SearchIndexMirror($writer, $pending);
        $document = null;
        $module = null;

        if (! $this->forget) {
            try {
                $source = $this->sourceOf($registry);

                if ($source instanceof IndexedSearchSourceInterface) {
                    $document = $source->document($this->sourceId);
                    $module = $registry->moduleOf($this->type);
                } elseif ($this->syncedByFreshProcess($store, $modules, $runner, $registry->unresolved() !== [])) {
                    return;
                }
            } catch (Throwable $e) {
                $this->warn('[Search] A search source failed; the document was left as it was.', $e);

                return;
            }
        }

        if (! $document instanceof SearchDocumentDTO) {
            $removed = $store->delete($this->type, $this->sourceId);

            if ($removed !== null) {
                $mirror->replayPendingRemovals();
                $mirror->remove([$removed]);
            }

            return;
        }

        $mirror->put([$store->upsert($this->type, $module, $document)]);
    }

    /**
     * Whether a fresh process synced the type without problems, for a type that may still have a source: some
     * registered source could not be loaded here, or the row belongs to an enabled module. A row of a disabled
     * module, a core row while every source loaded, and a failed or impossible sync answer false, so the caller
     * removes the row.
     */
    private function syncedByFreshProcess(
        SearchDocumentStoreInterface $store,
        EnabledModulesResolverInterface $modules,
        SearchSyncRunnerInterface $runner,
        bool $someSourcesUnresolved,
    ): bool {
        $owner = $store->moduleOf($this->type, $this->sourceId);

        if ($owner === null && ! $someSourcesUnresolved) {
            return false;
        }

        if ($owner !== null) {
            // A long-lived worker memoized the enabled modules at boot: read them again, as the sync does
            $modules->reset();

            if (! in_array($owner, $modules->names(), true)) {
                return false;
            }
        }

        try {
            if ($runner->sync($this->type)) {
                return true;
            }

            Log::warning('[Search] A fresh sync of a search type without source failed; the document was removed.', [
                'type' => $this->type,
                'source_id' => $this->sourceId,
                'module' => $owner,
            ]);
        } catch (Throwable $e) {
            $this->warn('[Search] A fresh sync of a search type without source could not run; the document was removed.', $e);
        }

        return false;
    }

    private function sourceOf(SearchSourceRegistryInterface $registry): ?IndexedSearchSourceInterface
    {
        foreach ($registry->indexedSources() as $source) {
            if ($source->type() === $this->type) {
                return $source;
            }
        }

        return null;
    }

    private function warn(string $message, Throwable $e): void
    {
        Log::warning($message, [
            'type' => $this->type,
            'source_id' => $this->sourceId,
            'exception' => $e,
        ]);
    }
}
