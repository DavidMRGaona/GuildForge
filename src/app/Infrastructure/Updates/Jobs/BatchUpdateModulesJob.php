<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Jobs;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\Application\Updates\Services\ModuleUpdaterInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class BatchUpdateModulesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800; // 30 minutes for batch

    /**
     * @param  array<string>|null  $moduleNames  Specific modules to update, or null for all
     */
    public function __construct(
        public readonly ?array $moduleNames = null,
    ) {}

    public function handle(
        ModuleUpdateCheckerInterface $updateChecker,
        ModuleUpdaterInterface $updater,
        ModuleManagerServiceInterface $moduleManager,
    ): void {
        Log::info('Starting batch module update');

        try {
            // Get available updates
            $availableUpdates = $updateChecker->checkAllForUpdates();

            if ($availableUpdates->isEmpty()) {
                Log::info('Batch update: No updates available');

                return;
            }

            // Filter to requested modules if specified
            if ($this->moduleNames !== null) {
                $availableUpdates = $availableUpdates->filter(
                    fn ($update) => in_array($update->moduleName, $this->moduleNames, true)
                );
            }

            // Sort by dependencies
            $sortedModules = $this->sortByDependencies($availableUpdates->toArray(), $moduleManager);

            $results = [
                'success' => [],
                'failed' => [],
                'skipped' => [],
            ];

            foreach ($sortedModules as $moduleName) {
                try {
                    // Check if a dependent module failed
                    if ($this->hasDependentFailed($moduleName, $results['failed'], $moduleManager)) {
                        $results['skipped'][] = $moduleName;
                        Log::warning("Skipping {$moduleName} due to failed dependency");

                        continue;
                    }

                    $result = $updater->update(new ModuleName($moduleName));

                    if ($result->isSuccess()) {
                        $results['success'][] = $moduleName;
                        Log::info("Updated module: {$moduleName}");
                    } else {
                        $results['failed'][] = $moduleName;
                        Log::warning("Failed to update module: {$moduleName}", [
                            'error' => $result->errorMessage,
                        ]);
                    }
                } catch (\Throwable $e) {
                    $results['failed'][] = $moduleName;
                    Log::error("Exception updating module: {$moduleName}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Batch module update completed', $results);
        } catch (\Throwable $e) {
            Log::error('Batch update job failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sort modules by dependencies (topological sort).
     *
     * @param  array<\App\Application\Updates\DTOs\AvailableUpdateDTO>  $updates
     * @return array<string>
     */
    private function sortByDependencies(array $updates, ModuleManagerServiceInterface $moduleManager): array
    {
        $moduleNames = array_map(fn ($u) => $u->moduleName, $updates);
        $dependencyGraph = [];

        foreach ($moduleNames as $name) {
            $module = $moduleManager->findByName($name);
            if ($module !== null) {
                $deps = $module->dependencies();
                // Only include dependencies that are also being updated
                $dependencyGraph[$name] = array_intersect($deps, $moduleNames);
            } else {
                $dependencyGraph[$name] = [];
            }
        }

        return $this->topologicalSort($dependencyGraph);
    }

    /**
     * Topological sort for dependency ordering.
     *
     * @param  array<string, array<string>>  $graph
     * @return array<string>
     */
    private function topologicalSort(array $graph): array
    {
        $sorted = [];
        $visited = [];
        $visiting = [];

        $visit = function (string $node) use (&$visit, &$sorted, &$visited, &$visiting, $graph): void {
            if (isset($visited[$node])) {
                return;
            }

            if (isset($visiting[$node])) {
                // Circular dependency - just proceed
                return;
            }

            $visiting[$node] = true;

            foreach ($graph[$node] ?? [] as $dep) {
                $visit($dep);
            }

            unset($visiting[$node]);
            $visited[$node] = true;
            $sorted[] = $node;
        };

        foreach (array_keys($graph) as $node) {
            $visit($node);
        }

        return $sorted;
    }

    /**
     * Check if any dependency of this module has failed.
     *
     * @param  array<string>  $failedModules
     */
    private function hasDependentFailed(
        string $moduleName,
        array $failedModules,
        ModuleManagerServiceInterface $moduleManager
    ): bool {
        $module = $moduleManager->findByName($moduleName);

        if ($module === null) {
            return false;
        }

        foreach ($module->dependencies() as $dep) {
            if (in_array($dep, $failedModules, true)) {
                return true;
            }
        }

        return false;
    }

    public function tags(): array
    {
        return ['updates', 'batch-update'];
    }
}
