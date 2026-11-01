<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Application\Knowledge\Services\KnowledgeCatalogSynchronizerInterface;
use Illuminate\Console\Command;

final class KnowledgeSyncCommand extends Command
{
    protected $signature = 'knowledge:sync';

    protected $description = 'Synchronize the knowledge base guides from the core and the enabled modules';

    public function handle(KnowledgeCatalogSynchronizerInterface $synchronizer): int
    {
        $report = $synchronizer->sync();

        $this->info('Inserted: '.$report->inserted);
        $this->info('Updated: '.$report->updated);
        $this->info('Deactivated: '.$report->deactivated);

        foreach ($report->errors as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
