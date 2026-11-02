<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Application\Knowledge\Services\KnowledgeCatalogSynchronizerInterface;
use App\Application\Knowledge\Services\KnowledgeIndexerInterface;
use Illuminate\Console\Command;

final class KnowledgeSyncCommand extends Command
{
    protected $signature = 'knowledge:sync';

    protected $description = 'Synchronize the knowledge base guides from the core and the enabled modules, then reindex them';

    public function handle(KnowledgeCatalogSynchronizerInterface $synchronizer, KnowledgeIndexerInterface $indexer): int
    {
        $report = $synchronizer->sync();

        $this->info('Inserted: '.$report->inserted);
        $this->info('Updated: '.$report->updated);
        $this->info('Deactivated: '.$report->deactivated);

        foreach ($report->errors as $error) {
            $this->warn($error);
        }

        // The search index is derived data: failing to update it never fails the sync (or the deploy).
        $index = $indexer->reindex();
        if ($index->error !== null) {
            $this->warn('Search index not updated: '.$index->error);
        } else {
            $this->info('Indexed: '.$index->indexed);
            $this->info('Removed from the index: '.$index->removed);
        }

        return self::SUCCESS;
    }
}
