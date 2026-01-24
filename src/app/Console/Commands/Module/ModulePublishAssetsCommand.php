<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class ModulePublishAssetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:publish-assets
        {module? : The name of the module (optional, publishes all if not specified)}
        {--all : Publish assets for all enabled modules}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish module assets to the public directory';

    public function __construct(
        private readonly ModuleManagerServiceInterface $moduleManager,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string|null $moduleName */
        $moduleName = $this->argument('module');
        $publishAll = (bool) $this->option('all');

        if ($moduleName !== null) {
            return $this->publishModuleAssets($moduleName);
        }

        if ($publishAll) {
            return $this->publishAllAssets();
        }

        $this->error('Please specify a module name or use --all to publish all module assets.');

        return self::FAILURE;
    }

    private function publishModuleAssets(string $moduleName): int
    {
        $module = $this->moduleManager->find(new ModuleName($moduleName));

        if ($module === null) {
            $this->error("Module '{$moduleName}' not found.");

            return self::FAILURE;
        }

        $sourcePath = $module->path().'/resources/assets';
        $destinationPath = public_path('modules/'.$moduleName);

        if (! is_dir($sourcePath)) {
            $this->warn("No assets found for module '{$moduleName}'.");

            return self::SUCCESS;
        }

        if (is_dir($destinationPath)) {
            File::deleteDirectory($destinationPath);
        }

        File::copyDirectory($sourcePath, $destinationPath);

        $this->info("Assets for module '{$moduleName}' published successfully.");

        return self::SUCCESS;
    }

    private function publishAllAssets(): int
    {
        $modules = $this->moduleManager->enabled();
        $published = 0;

        foreach ($modules->all() as $module) {
            $sourcePath = $module->path().'/resources/assets';

            if (! is_dir($sourcePath)) {
                continue;
            }

            $moduleName = $module->name()->value;
            $destinationPath = public_path('modules/'.$moduleName);

            if (is_dir($destinationPath)) {
                File::deleteDirectory($destinationPath);
            }

            File::copyDirectory($sourcePath, $destinationPath);
            $this->line("  <fg=green>[PUBLISHED]</> {$moduleName}");
            $published++;
        }

        if ($published === 0) {
            $this->warn('No module assets found to publish.');
        } else {
            $this->newLine();
            $this->info("Published assets for {$published} module(s).");
        }

        return self::SUCCESS;
    }
}
