<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\Application\Updates\Services\ModuleUpdaterInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Console\Command;

final class UpdateModuleCommand extends Command
{
    protected $signature = 'module:update
                            {name? : Module name to update}
                            {--all : Update all modules with available updates}
                            {--dry-run : Show what would be updated without applying}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Update a module or all modules';

    public function handle(
        ModuleUpdateCheckerInterface $updateChecker,
        ModuleUpdaterInterface $updater,
    ): int {
        $name = $this->argument('name');
        $updateAll = $this->option('all');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (! $name && ! $updateAll) {
            $this->error('Please specify a module name or use --all');

            return self::FAILURE;
        }

        if ($updateAll) {
            return $this->updateAllModules($updateChecker, $updater, $dryRun, $force);
        }

        return $this->updateSingleModule($name, $updater, $dryRun, $force);
    }

    private function updateSingleModule(
        string $name,
        ModuleUpdaterInterface $updater,
        bool $dryRun,
        bool $force,
    ): int {
        $moduleName = new ModuleName($name);

        try {
            $preview = $updater->preview($moduleName);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Module: {$preview->moduleName}");
        $this->info("Current version: {$preview->fromVersion}");
        $this->info("Available version: {$preview->toVersion}");

        if ($preview->isMajorUpdate) {
            $this->warn('This is a major version update!');
        }

        if (! $preview->coreCompatible) {
            $this->error("Incompatible with current core version. Requires: {$preview->coreRequirement}");

            return self::FAILURE;
        }

        if ($preview->hasMigrations()) {
            $this->info('Pending migrations: ' . count($preview->pendingMigrations));
        }

        if ($dryRun) {
            $this->info('Dry run - no changes made.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm('Do you want to proceed with the update?')) {
            $this->info('Update cancelled.');

            return self::SUCCESS;
        }

        $this->info('Starting update...');

        $result = $updater->update($moduleName);

        if ($result->isSuccess()) {
            $this->info("Successfully updated {$name} to {$result->toVersion}");

            return self::SUCCESS;
        }

        if ($result->wasRolledBack()) {
            $this->warn("Update failed but was rolled back. Error: {$result->errorMessage}");
        } else {
            $this->error("Update failed: {$result->errorMessage}");
        }

        return self::FAILURE;
    }

    private function updateAllModules(
        ModuleUpdateCheckerInterface $updateChecker,
        ModuleUpdaterInterface $updater,
        bool $dryRun,
        bool $force,
    ): int {
        $updates = $updateChecker->checkAllForUpdates();

        if ($updates->isEmpty()) {
            $this->info('All modules are up to date.');

            return self::SUCCESS;
        }

        $this->info("Found {$updates->count()} update(s):");

        foreach ($updates as $update) {
            $this->info("  - {$update->moduleName}: {$update->currentVersion} → {$update->availableVersion}");
        }

        if ($dryRun) {
            $this->info('Dry run - no changes made.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm('Do you want to update all modules?')) {
            $this->info('Update cancelled.');

            return self::SUCCESS;
        }

        $success = 0;
        $failed = 0;

        foreach ($updates as $update) {
            $this->info("Updating {$update->moduleName}...");

            $result = $updater->update(new ModuleName($update->moduleName));

            if ($result->isSuccess()) {
                $this->info("  ✓ Updated to {$result->toVersion}");
                $success++;
            } else {
                $this->error("  ✗ Failed: {$result->errorMessage}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Update complete: {$success} succeeded, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
