<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Application\Updates\Services\ModuleHealthCheckerInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Modules\Services\ModuleSchemaGuard;
use App\Infrastructure\Modules\Services\ModuleSeederRunner;
use Illuminate\Console\Command;

/**
 * Second half of a module update, run by the updater in a fresh PHP process right
 * after the new files are in place.
 *
 * The queue worker that swapped the files still has the old module classes and
 * providers loaded, so running migrations, seeders or the health check there mixes
 * old and new code. A new process boots with the new version only.
 */
final class FinishModuleUpdateCommand extends Command
{
    protected $signature = 'module:finish-update {name : Module name}';

    protected $description = 'Run migrations, seeders and the health check of a module whose files were just updated (used by the updater)';

    protected $hidden = true;

    public function handle(
        ModuleRepositoryInterface $modules,
        ModuleSchemaGuard $schemaGuard,
        ModuleSeederRunner $seederRunner,
        ModuleHealthCheckerInterface $healthChecker,
    ): int {
        try {
            $name = new ModuleName((string) $this->argument('name'));
            $module = $modules->findByName($name);

            if ($module === null) {
                $this->error("Module '{$name->value}' is not installed.");

                return self::FAILURE;
            }

            $migrations = $module->path().'/database/migrations';

            if (is_dir($migrations)) {
                $exitCode = $schemaGuard->protect($name->value, fn (): int => $this->call('migrate', [
                    '--path' => $migrations,
                    '--realpath' => true,
                    '--force' => true,
                ]));

                if ($exitCode !== self::SUCCESS) {
                    $this->error("Migrations of {$name->value} failed.");

                    return self::FAILURE;
                }
            }

            // Loads every seeder first, so seeders can call each other
            $seederRunner->run($module);

            if ((bool) config('updates.behavior.health_check', true)) {
                $health = $healthChecker->check($name);

                if (! $health->passes()) {
                    $this->error('Health check failed: '.implode(', ', $health->errors));

                    return self::FAILURE;
                }
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->refreshCaches();
        $this->info("{$module->name()->value} is ready.");

        return self::SUCCESS;
    }

    /**
     * Rebuild the caches the new version may invalidate (routes, views, config).
     * Done here, in a process that only knows the new code, and only re-cached
     * when the site was using cached config/routes in the first place.
     */
    private function refreshCaches(): void
    {
        if ($this->laravel->runningUnitTests()) {
            return;
        }

        $configCached = $this->laravel->configurationIsCached();
        $routesCached = $this->laravel->routesAreCached();

        $this->callSilently('optimize:clear');

        if ($configCached) {
            $this->callSilently('config:cache');
            $this->callSilently('view:cache');
        }

        if ($routesCached) {
            $this->callSilently('route:cache');
        }
    }
}
