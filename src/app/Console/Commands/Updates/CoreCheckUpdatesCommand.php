<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use Illuminate\Console\Command;

final class CoreCheckUpdatesCommand extends Command
{
    protected $signature = 'core:check-updates';

    protected $description = 'Compare the deployed core commit with the branch deployments are built from';

    public function handle(CoreUpdateCheckerInterface $updateChecker): int
    {
        try {
            $status = $updateChecker->check();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Deployed commit: '.substr($status->deployedCommit, 0, 7)." (branch {$status->branch})");

        if ($status->diverged) {
            $this->warn("The deployed commit is not on {$status->branch}.");
        }

        if ($status->isUpToDate()) {
            $this->info("The deployed commit is the latest on {$status->branch}.");

            return self::SUCCESS;
        }

        $this->warn("{$status->behindBy} commit(s) on {$status->branch} are not deployed:");

        foreach ($status->commits as $commit) {
            $this->line('  '.substr($commit['sha'], 0, 7).'  '.$commit['message']);
        }

        return self::SUCCESS;
    }
}
