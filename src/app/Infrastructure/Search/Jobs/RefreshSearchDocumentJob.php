<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Jobs;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Application\Search\Services\SearchSourceRegistryInterface;
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
 * does not (or when the record is forgotten, or no source owns the type any more).
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
                } elseif ($registry->unresolved() !== []) {
                    // The type may belong to a source that could not be loaded: never drop its row for that
                    Log::warning('[Search] No source could be found for a search document while some could not be loaded; it was left as it was.', [
                        'type' => $this->type,
                        'source_id' => $this->sourceId,
                    ]);

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
