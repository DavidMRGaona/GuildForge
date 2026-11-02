<?php

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Application\Knowledge\Services\KnowledgeMetricsServiceInterface;
use Illuminate\Console\Command;

final class KnowledgePruneMetricsCommand extends Command
{
    protected $signature = 'knowledge:prune-metrics {--days= : Days to keep (defaults to knowledge.metrics_retention_days)}';

    protected $description = 'Delete knowledge base votes and failed searches older than the retention period';

    public function handle(KnowledgeMetricsServiceInterface $metrics): int
    {
        $option = $this->option('days');
        $days = $option === null || $option === ''
            ? config('knowledge.metrics_retention_days')
            : $option;

        if (! is_numeric($days) || (int) $days < 0 || (string) (int) $days !== (string) $days) {
            $this->error('The --days option must be a non-negative integer.');

            return self::FAILURE;
        }

        $this->info('Pruned: '.$metrics->prune((int) $days));

        return self::SUCCESS;
    }
}
