<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Jobs;

use App\Application\Knowledge\Services\KnowledgeCatalogSynchronizerInterface;
use App\Application\Knowledge\Services\KnowledgeIndexerInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Brings the guide catalog and the search index in line after modules or branding settings change.
 */
final class SyncKnowledgeCatalogJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Seconds the uniqueness lock lasts, so a burst of changes queues a single sync */
    public int $uniqueFor = 300;

    public function handle(KnowledgeCatalogSynchronizerInterface $synchronizer, KnowledgeIndexerInterface $indexer): void
    {
        $synchronizer->sync();
        $indexer->reindex();
    }
}
