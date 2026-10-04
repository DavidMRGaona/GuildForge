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

        $result = $updateChecker->checkAll((bool) $this->option('force'));
        $updates = $result->updates;

        foreach ($result->errors as $module => $error) {
            $this->error("{$module}: {$error}");
        }

        if ($result->modulesWithoutSource !== []) {
            $this->warn('No repository configured for: '.implode(', ', $result->modulesWithoutSource));
        }

        $exitCode = $result->hasErrors() ? self::FAILURE : self::SUCCESS;

        if ($updates->isEmpty()) {
            if (! $result->hasErrors()) {
                $this->info('All modules are up to date.');
            }

            return $exitCode;
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
                $update->publishedAt?->format('Y-m-d') ?? '-',
            ];
        }

        $this->table(
            ['Module', 'Current', 'Available', 'Major', 'Published'],
            $rows
        );

        return $exitCode;
    }
}
