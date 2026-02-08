<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Copies pre-built Vite assets from module directories to public/build/modules/.
 *
 * This command scans module directories on disk (not the database) so it works
 * before module:discover has run. It is idempotent and safe to call on every
 * container start to ensure module manifest.json files are always available.
 */
final class ModulePublishBuildAssetsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'module:publish-build-assets';

    /**
     * @var string
     */
    protected $description = 'Publish pre-built Vite assets from all modules to public/build/modules/';

    public function handle(): int
    {
        /** @var string $modulesPath */
        $modulesPath = config('modules.path', base_path('modules'));

        if (! File::isDirectory($modulesPath)) {
            $this->info('No modules directory found.');

            return self::SUCCESS;
        }

        $directories = File::directories($modulesPath);

        if (count($directories) === 0) {
            $this->info('No modules found.');

            return self::SUCCESS;
        }

        $published = 0;

        foreach ($directories as $moduleDir) {
            $moduleName = basename($moduleDir);
            $sourceBuild = $moduleDir.'/public/build';

            if (! File::isDirectory($sourceBuild)) {
                continue;
            }

            $targetBuild = public_path("build/modules/{$moduleName}");

            if (! File::isDirectory(dirname($targetBuild))) {
                File::makeDirectory(dirname($targetBuild), 0755, true);
            }

            if (File::isDirectory($targetBuild)) {
                File::deleteDirectory($targetBuild);
            }

            File::copyDirectory($sourceBuild, $targetBuild);
            $this->line("  <fg=green>[PUBLISHED]</> {$moduleName}");
            $published++;
        }

        if ($published === 0) {
            $this->info('No modules with pre-built assets found.');
        } else {
            $this->newLine();
            $this->info("Published build assets for {$published} module(s).");
            Log::info("module:publish-build-assets: published assets for {$published} module(s)");
        }

        return self::SUCCESS;
    }
}
