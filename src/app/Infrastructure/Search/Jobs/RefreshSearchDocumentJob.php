<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Jobs;

use App\Application\Search\Contracts\IndexedSearchSourceInterface;
use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Application\Search\Services\SearchDocumentStoreInterface;
use App\Application\Search\Services\SearchSourceRegistryInterface;
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
 * an exception would reach the request, and the nightly sync puts either right.
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
    ): void {
        $document = null;
        $module = null;

        if (! $this->forget) {
            try {
                $source = $this->sourceOf($registry);

                if ($source instanceof IndexedSearchSourceInterface) {
                    $document = $source->document($this->sourceId);
                    $module = $registry->moduleOf($this->type);
                }
            } catch (Throwable $e) {
                $this->warn('[Search] A search source failed; the document was left as it was.', $e);

                return;
            }
        }

        if (! $document instanceof SearchDocumentDTO) {
            $removed = $store->delete($this->type, $this->sourceId);

            if ($removed !== null) {
                $this->mirror(static function () use ($writer, $removed): void {
                    $writer->remove([$removed]);
                });
            }

            return;
        }

        $row = $store->upsert($this->type, $module, $document);
        $this->mirror(static function () use ($writer, $row): void {
            $writer->put([$row]);
        });
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

    /**
     * @param  callable(): void  $write
     */
    private function mirror(callable $write): void
    {
        try {
            $write();
        } catch (Throwable $e) {
            $this->warn('[Search] The search index could not be updated.', $e);
        }
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
