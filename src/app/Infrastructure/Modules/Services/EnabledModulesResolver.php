<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\RejectedModuleDTO;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Modules\Services\ModuleCompatibilityServiceInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Exceptions\InvalidModuleNameException;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class EnabledModulesResolver implements EnabledModulesResolverInterface
{
    /** @var list<Module>|null */
    private ?array $approved = null;

    /** @var list<RejectedModuleDTO> */
    private array $rejected = [];

    /** @var array<string, list<string>> Keyed by modules path */
    private array $migrationPaths = [];

    public function __construct(
        private readonly ModuleRepositoryInterface $repository,
        private readonly ModuleCompatibilityServiceInterface $compatibility,
    ) {
    }

    public function names(): array
    {
        return array_map(static fn (Module $module): string => $module->name()->value, $this->modules());
    }

    public function modules(): array
    {
        return $this->approved ?? $this->resolve();
    }

    public function rejected(): array
    {
        $this->modules();

        return $this->rejected;
    }

    public function migrationPaths(): array
    {
        $modulesPath = rtrim((string) config('modules.path', base_path('modules')), '/');

        if (isset($this->migrationPaths[$modulesPath])) {
            return $this->migrationPaths[$modulesPath];
        }

        $paths = [];

        foreach ((is_dir($modulesPath) ? scandir($modulesPath) : false) ?: [] as $directory) {
            // Dot directories are the updater's staging/previous copies, never modules
            if (str_starts_with($directory, '.')) {
                continue;
            }

            $migrations = "{$modulesPath}/{$directory}/database/migrations";

            if (! is_dir($migrations)) {
                continue;
            }

            try {
                $name = ModuleName::fromString($directory);
            } catch (InvalidModuleNameException) {
                continue;
            }

            if ($this->compatibility->checkInstalled($name)->isCompatible()) {
                $paths[] = $migrations;
            }
        }

        return $this->migrationPaths[$modulesPath] = $paths;
    }

    public function reset(): void
    {
        $this->approved = null;
        $this->rejected = [];
        $this->migrationPaths = [];
    }

    /**
     * @return list<Module>
     */
    private function resolve(): array
    {
        $approved = [];
        $rejected = [];

        foreach ($this->enabledInDatabase() as $module) {
            $result = $this->compatibility->checkInstalled($module->name());

            if ($result->isCompatible()) {
                $approved[] = $module;

                continue;
            }

            $rejected[] = new RejectedModuleDTO($module->name()->value, $module->displayName(), $result->issues);

            // Web requests show the panel banner instead of logging once per request
            if (app()->runningInConsole() && $this->firstReportToday($module->name()->value, $result->summary())) {
                Log::warning('[EnabledModulesResolver] Module rejected', [
                    'module' => $module->name()->value,
                    'reasons' => $result->summary(),
                ]);
            }
        }

        $this->rejected = $rejected;

        return $this->approved = $approved;
    }

    /**
     * The scheduler starts a console process every minute: without a shared marker the same
     * rejection would be logged ~1440 times a day. A new reason is logged at once.
     */
    private function firstReportToday(string $module, string $reasons): bool
    {
        try {
            return Cache::add('modules.compatibility.logged.'.$module.'.'.md5($reasons), true, now()->addDay());
        } catch (\Throwable) {
            // Without a cache store, logging every time beats hiding the rejection or failing the boot
            return true;
        }
    }

    /**
     * Only the database list is cached; the compatibility filter runs after the cache, so a
     * rejection is never persisted and a new host image is evaluated on its first boot.
     *
     * @return list<Module>
     */
    private function enabledInDatabase(): array
    {
        if (! $this->modulesTableExists()) {
            return [];
        }

        if ((bool) config('modules.cache.enabled', false)) {
            /** @var array<Module> $cached */
            $cached = Cache::remember(
                (string) config('modules.cache.key', 'modules.discovered'),
                (int) config('modules.cache.ttl', 3600),
                fn (): array => $this->repository->enabled()->all(),
            );

            return array_values($cached);
        }

        return array_values($this->repository->enabled()->all());
    }

    private function modulesTableExists(): bool
    {
        try {
            return Schema::hasTable('modules');
        } catch (\Throwable) {
            return false;
        }
    }
}
