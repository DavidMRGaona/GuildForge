<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use Illuminate\Console\Command;

final class CheckModuleUpdatesCommand extends Command
{
    protected $signature = 'module:check-updates
                            {--force : Force check, ignoring cache}';

    protected $description = 'Check for available module updates';

    public function handle(ModuleUpdateCheckerInterface $updateChecker): int
    {
        $this->info('Checking for module updates...');

        $updates = $updateChecker->checkAllForUpdates();

        if ($updates->isEmpty()) {
            $this->info('All modules are up to date.');

            return self::SUCCESS;
        }

        $this->info("Found {$updates->count()} update(s) available:");
        $this->newLine();

        $rows = [];
        foreach ($updates as $update) {
            $rows[] = [
                $update->moduleName,
                $update->currentVersion,
                $update->availableVersion,
                $update->isMajorUpdate ? 'Yes' : 'No',
                $update->publishedAt->format('Y-m-d'),
            ];
        }

        $this->table(
            ['Module', 'Current', 'Available', 'Major', 'Published'],
            $rows
        );

        return self::SUCCESS;
    }
}
