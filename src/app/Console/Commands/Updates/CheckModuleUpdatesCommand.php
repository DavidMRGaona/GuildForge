<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Application\Updates\DTOs\BlockedReleaseDTO;
use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\View\Modules\CompatibilityIssueFormatter;
use Illuminate\Console\Command;

final class CheckModuleUpdatesCommand extends Command
{
    protected $signature = 'module:check-updates
                            {--force : Force check, ignoring cache}';

    protected $description = 'Check for available module updates';

    public function handle(ModuleUpdateCheckerInterface $updateChecker, CompatibilityIssueFormatter $formatter): int
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

        if ($updates->isEmpty()) {
            if (! $result->hasErrors()) {
                $this->info('All modules are up to date.');
            }
        } else {
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

            $this->table(['Module', 'Current', 'Available', 'Major', 'Published'], $rows);
        }

        // Shown, never applied: they need a newer core. They do not change the exit code
        if ($result->blocked !== []) {
            $this->newLine();
            $this->warn(count($result->blocked).' newer release(s) cannot run on this site:');
            $this->table(['Module', 'Current', 'Blocked', 'Reason'], array_map(
                static fn (BlockedReleaseDTO $blocked): array => [
                    $blocked->moduleName,
                    $blocked->currentVersion,
                    $blocked->blockedVersion,
                    $formatter->summary($blocked->issues, short: true),
                ],
                $result->blocked,
            ));
        }

        return $result->hasErrors() ? self::FAILURE : self::SUCCESS;
    }
}
