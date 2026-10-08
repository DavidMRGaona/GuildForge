<?php

declare(strict_types=1);

namespace App\Console\Commands\Content;

use App\Application\Content\Services\TrixContentMigratorInterface;
use Illuminate\Console\Command;

final class ConvertTrixContentCommand extends Command
{
    protected $signature = 'content:convert-trix
                            {--dry-run : List what would change without writing anything}
                            {--restore= : Put back the values saved in this backup file}';

    protected $description = 'Convert rich text written with the Trix editor to the HTML of the TipTap editor';

    public function handle(TrixContentMigratorInterface $migrator): int
    {
        $restore = $this->option('restore');

        if (is_string($restore) && $restore !== '') {
            $this->info("Restored {$migrator->restore($restore)} value(s) from {$restore}.");

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $report = $migrator->migrate($dryRun);

        foreach ($report->changed as $target) {
            $this->line("  {$target}");
        }

        $verb = $dryRun ? 'would change' : 'changed';
        $this->info("{$report->checked} value(s) checked, ".count($report->changed)." {$verb}.");

        if ($report->backupPath !== null) {
            $this->info("Original values saved to {$report->backupPath}");
        }

        return self::SUCCESS;
    }
}
