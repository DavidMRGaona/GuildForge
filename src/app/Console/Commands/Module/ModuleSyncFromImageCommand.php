<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Domain\Modules\ValueObjects\ModuleVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

final class ModuleSyncFromImageCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'module:sync-from-image {path : Path to the modules image directory}';

    /**
     * @var string
     */
    protected $description = 'Sync modules from the Docker image, preserving ZIP-updated modules in the volume';

    public function handle(): int
    {
        $imagePath = $this->argument('path');

        if (! is_string($imagePath) || ! File::isDirectory($imagePath)) {
            $this->warn("Image path does not exist: {$imagePath}");

            return self::SUCCESS;
        }

        $modulesPath = config('modules.path', base_path('modules'));

        if (! File::isDirectory($modulesPath)) {
            File::makeDirectory($modulesPath, 0755, true);
        }

        $imageModules = File::directories($imagePath);

        if (count($imageModules) === 0) {
            $this->info('No modules found in image.');

            return self::SUCCESS;
        }

        foreach ($imageModules as $imageModuleDir) {
            $moduleName = basename($imageModuleDir);
            $volumeModuleDir = $modulesPath.'/'.$moduleName;

            if (! File::isDirectory($volumeModuleDir)) {
                $this->syncModule($imageModuleDir, $volumeModuleDir, $moduleName, 'first deploy');

                continue;
            }

            $this->syncByVersion($imageModuleDir, $volumeModuleDir, $moduleName);
        }

        return self::SUCCESS;
    }

    private function syncByVersion(string $imageDir, string $volumeDir, string $moduleName): void
    {
        $imageVersion = $this->readVersion($imageDir);
        $volumeVersion = $this->readVersion($volumeDir);

        if ($imageVersion === null) {
            $this->warn("Skipping {$moduleName}: no valid module.json in image");

            return;
        }

        if ($volumeVersion === null) {
            $this->syncModule($imageDir, $volumeDir, $moduleName, 'volume has no valid manifest');

            return;
        }

        if ($imageVersion->isGreaterThan($volumeVersion)) {
            $this->syncModule($imageDir, $volumeDir, $moduleName, "image v{$imageVersion->value()} > volume v{$volumeVersion->value()}");

            return;
        }

        $this->info("Keeping {$moduleName} v{$volumeVersion->value()} from volume (image has v{$imageVersion->value()})");
        Log::info("module:sync-from-image: keeping {$moduleName} from volume", [
            'volume_version' => $volumeVersion->value(),
            'image_version' => $imageVersion->value(),
        ]);
    }

    private function syncModule(string $source, string $target, string $moduleName, string $reason): void
    {
        if (File::isDirectory($target)) {
            File::deleteDirectory($target);
        }

        File::copyDirectory($source, $target);
        $this->copyPreBuiltAssets($target, $moduleName);

        $this->info("Synced {$moduleName} from image ({$reason})");
        Log::info("module:sync-from-image: synced {$moduleName}", ['reason' => $reason]);
    }

    private function copyPreBuiltAssets(string $moduleDir, string $moduleName): void
    {
        $sourceBuild = $moduleDir.'/public/build';

        if (! File::isDirectory($sourceBuild)) {
            return;
        }

        try {
            $targetBuild = public_path("build/modules/{$moduleName}");

            if (! File::isDirectory(dirname($targetBuild))) {
                File::makeDirectory(dirname($targetBuild), 0755, true);
            }

            File::copyDirectory($sourceBuild, $targetBuild);

            $this->info("  Copied pre-built assets for {$moduleName}");
        } catch (\Throwable $e) {
            Log::warning("Failed to copy pre-built assets for module {$moduleName}: {$e->getMessage()}");
            $this->warn("  Failed to copy pre-built assets for {$moduleName}: {$e->getMessage()}");
        }
    }

    private function readVersion(string $moduleDir): ?ModuleVersion
    {
        $manifestPath = $moduleDir.'/module.json';

        if (! File::exists($manifestPath)) {
            return null;
        }

        $content = File::get($manifestPath);
        /** @var array<string, mixed>|null $data */
        $data = json_decode($content, true);

        if (! is_array($data) || ! isset($data['version']) || ! is_string($data['version'])) {
            return null;
        }

        try {
            return ModuleVersion::fromString($data['version']);
        } catch (\Throwable) {
            return null;
        }
    }
}
