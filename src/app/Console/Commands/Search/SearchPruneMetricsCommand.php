<?php

declare(strict_types=1);

namespace App\Console\Commands\Search;

use App\Application\Search\Services\SearchMetricsServiceInterface;
use Illuminate\Console\Command;

final class SearchPruneMetricsCommand extends Command
{
    protected $signature = 'search:prune-metrics {--days= : Days to keep (defaults to knowledge.metrics_retention_days)}';

    protected $description = 'Delete site search misses older than the retention period';

    public function handle(SearchMetricsServiceInterface $metrics): int
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
