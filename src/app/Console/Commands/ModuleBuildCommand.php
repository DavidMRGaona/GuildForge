<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Modules\Services\ModuleAssetBuilder;
use Illuminate\Console\Command;

final class ModuleBuildCommand extends Command
{
    protected $signature = 'module:build
                            {name? : The name of the module to build}
                            {--all : Build all modules with Vue components}
                            {--force : Force rebuild even if assets already exist}';

    protected $description = 'Build Vue component assets for a module';

    public function __construct(
        private readonly ModuleAssetBuilder $assetBuilder,
        private readonly ModuleRepositoryInterface $moduleRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $moduleName = $this->argument('name');
        $buildAll = $this->option('all');
        $force = $this->option('force');

        if (! $moduleName && ! $buildAll) {
            $this->error('Please specify a module name or use --all to build all modules.');
            $this->newLine();
            $this->line('Usage: php artisan module:build <module-name>');
            $this->line('       php artisan module:build --all');

            return Command::FAILURE;
        }

        if ($buildAll) {
            return $this->buildAllModules($force);
        }

        return $this->buildModule($moduleName, $force);
    }

    private function buildModule(string $moduleName, bool $force): int
    {
        $module = $this->moduleRepository->findByName(ModuleName::fromString($moduleName));

        if ($module === null) {
            $this->error("Module not found: {$moduleName}");

            return Command::FAILURE;
        }

        if (! $this->assetBuilder->hasComponents($module)) {
            $this->info("Module {$moduleName} has no Vue components to build.");

            return Command::SUCCESS;
        }

        if (! $this->assetBuilder->hasBuildConfig($module)) {
            $this->error("Module {$moduleName} has Vue components but no build configuration.");
            $this->line('Ensure the module has both package.json and vite.config.ts files.');

            return Command::FAILURE;
        }

        $this->info("Building assets for module: {$moduleName}");

        $success = $this->assetBuilder->build($module, $force);

        if ($success) {
            $this->info("Successfully built assets for {$moduleName}");

            return Command::SUCCESS;
        }

        $this->error("Failed to build assets for {$moduleName}");
        $this->line('Check the Laravel log for details.');

        return Command::FAILURE;
    }

    private function buildAllModules(bool $force): int
    {
        $modules = $this->moduleRepository->all();

        if ($modules->isEmpty()) {
            $this->info('No modules found.');

            return Command::SUCCESS;
        }

        $built = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($modules as $module) {
            $moduleName = $module->name()->value;

            if (! $this->assetBuilder->hasComponents($module)) {
                $this->line("Skipping {$moduleName} (no Vue components)");
                $skipped++;

                continue;
            }

            if (! $this->assetBuilder->hasBuildConfig($module)) {
                $this->warn("Skipping {$moduleName} (no build configuration)");
                $skipped++;

                continue;
            }

            $this->info("Building: {$moduleName}");

            $success = $this->assetBuilder->build($module, $force);

            if ($success) {
                $built++;
            } else {
                $this->error("Failed: {$moduleName}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Build complete: {$built} built, {$skipped} skipped, {$failed} failed");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
