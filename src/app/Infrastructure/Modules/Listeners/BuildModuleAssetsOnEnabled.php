<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Listeners;

use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Modules\Services\ModuleAssetBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Builds Vue component assets when a module is enabled.
 *
 * This listener runs asynchronously (queued) to avoid blocking the
 * enable operation. Asset building involves npm install and build,
 * which can take several seconds.
 */
final class BuildModuleAssetsOnEnabled implements ShouldQueue
{
    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    public function __construct(
        private readonly ModuleAssetBuilder $assetBuilder,
        private readonly ModuleRepositoryInterface $moduleRepository,
    ) {
    }

    public function handle(ModuleEnabled $event): void
    {
        try {
            $module = $this->moduleRepository->findByName(ModuleName::fromString($event->moduleName));

            if ($module === null) {
                Log::warning("BuildModuleAssetsOnEnabled: Module not found: {$event->moduleName}");

                return;
            }

            if (! $this->assetBuilder->hasComponents($module)) {
                Log::debug("BuildModuleAssetsOnEnabled: Module {$event->moduleName} has no Vue components");

                return;
            }

            if (! $this->assetBuilder->hasBuildConfig($module)) {
                Log::warning("BuildModuleAssetsOnEnabled: Module {$event->moduleName} has components but no build config");

                return;
            }

            $success = $this->assetBuilder->build($module);

            if ($success) {
                Log::info("BuildModuleAssetsOnEnabled: Successfully built assets for {$event->moduleName}");
            } else {
                Log::error("BuildModuleAssetsOnEnabled: Failed to build assets for {$event->moduleName}");
            }
        } catch (\Throwable $e) {
            Log::error("BuildModuleAssetsOnEnabled: Exception while building assets for {$event->moduleName}", [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
