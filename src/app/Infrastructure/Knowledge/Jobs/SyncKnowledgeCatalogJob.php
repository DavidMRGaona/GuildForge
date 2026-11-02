<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Jobs;

use App\Application\Knowledge\Services\KnowledgeCatalogSynchronizerInterface;
use App\Application\Knowledge\Services\KnowledgeIndexerInterface;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Brings the guide catalog and the search index in line after modules or branding settings change.
 */
final class SyncKnowledgeCatalogJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Seconds the uniqueness lock lasts, so a burst of changes queues a single sync */
    public int $uniqueFor = 300;

    /**
     * Queues a sync without ever failing the caller: with a sync queue the job runs inline, and a broken queue throws on dispatch.
     */
    public static function dispatchSafely(): void
    {
        try {
            self::dispatch();
        } catch (Throwable $e) {
            Log::warning('[Knowledge] Could not sync the guide catalog.', ['exception' => $e]);
        }
    }

    public function handle(
        KnowledgeCatalogSynchronizerInterface $synchronizer,
        KnowledgeIndexerInterface $indexer,
        EnabledModulesResolverInterface $modules,
    ): void {
        // A long-lived worker memoized the enabled modules at boot: read them again so a module enabled since then counts
        $modules->reset();
        $synchronizer->sync();
        $indexer->reindex();
    }
}
