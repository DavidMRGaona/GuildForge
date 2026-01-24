<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\DependencyCheckResultDTO;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Collections\ModuleCollection;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleDiscovered;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleUninstalled;
use App\Domain\Modules\Exceptions\ModuleAlreadyDisabledException;
use App\Domain\Modules\Exceptions\ModuleAlreadyEnabledException;
use App\Domain\Modules\Exceptions\ModuleCannotUninstallException;
use App\Domain\Modules\Exceptions\ModuleDependencyException;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

final readonly class ModuleManagerService implements ModuleManagerServiceInterface
{
    public function __construct(
        private ModuleRepositoryInterface $repository,
        private ModuleDiscoveryService $discoveryService,
        private ModuleDependencyResolver $dependencyResolver,
        private ModuleMigrationRunner $migrationRunner,
        private Dispatcher $events,
    ) {}

    public function discover(): ModuleCollection
    {
        $manifests = $this->discoveryService->discover();
        $discovered = new ModuleCollection;

        foreach ($manifests as $manifest) {
            $moduleName = new ModuleName($manifest->name);

            // Check if module already exists in database
            if ($this->repository->exists($moduleName)) {
                // Get existing module
                $existing = $this->repository->findByName($moduleName);
                if ($existing !== null) {
                    $discovered->add($existing);
                }

                continue;
            }

            // Compute display_name from manifest
            $displayName = $manifest->description ?? $this->studlyCase($manifest->name);
            $modulesPath = config('modules.path', base_path('modules'));

            // Create new module from manifest
            $module = new Module(
                id: ModuleId::generate(),
                name: $moduleName,
                displayName: $displayName,
                description: $manifest->description ?? '',
                version: ModuleVersion::fromString($manifest->version),
                author: $manifest->author ?? '',
                requirements: $this->parseRequirements($manifest->requires),
                status: ModuleStatus::Disabled,
                enabledAt: null,
                createdAt: new DateTimeImmutable,
                updatedAt: new DateTimeImmutable,
                namespace: $manifest->namespace,
                provider: $manifest->provider,
                path: $modulesPath.'/'.$manifest->name,
                dependencies: $manifest->dependencies ?? [],
            );

            $this->repository->save($module);
            $discovered->add($module);

            // Dispatch event
            $this->events->dispatch(new ModuleDiscovered(
                $module->id()->value,
                $module->name()->value,
                $module->version()->value(),
            ));
        }

        return $discovered;
    }

    public function enable(ModuleName $name): Module
    {
        $module = $this->repository->findByName($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        if ($module->isEnabled()) {
            throw ModuleAlreadyEnabledException::withName($name->value);
        }

        // Check dependencies
        $depCheck = $this->checkDependencies($name);
        if ($depCheck->hasErrors()) {
            throw ModuleDependencyException::missingDependency(
                $name->value,
                $this->getFirstMissingDependency($depCheck)
            );
        }

        // Run migrations only if the module has not been installed yet
        // This prevents re-running migrations when enabling a previously disabled module
        if (! $module->isInstalled()) {
            try {
                $this->migrate($name);
                $module->markInstalled();
            } catch (ModuleNotFoundException) {
                // Module directory doesn't exist yet, that's okay
                // Still mark as installed since there are no migrations to run
                $module->markInstalled();
            }
        }

        // Enable the module
        $module->enable();
        $this->repository->save($module);

        // Invalidate cache
        $this->invalidateModuleCache();

        // Dispatch event
        $this->events->dispatch(new ModuleEnabled(
            $module->id()->value,
            $module->name()->value,
        ));

        return $module;
    }

    public function disable(ModuleName $name): Module
    {
        $module = $this->repository->findByName($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        if ($module->isDisabled()) {
            throw ModuleAlreadyDisabledException::withName($name->value);
        }

        // Check if other enabled modules depend on this one
        $allModules = $this->repository->all()->all();
        $dependents = $this->dependencyResolver->getDependents($module, $allModules);
        $enabledDependents = [];

        foreach ($dependents as $dependent) {
            if ($dependent->isEnabled()) {
                $enabledDependents[] = $dependent->name()->value;
            }
        }

        if (! empty($enabledDependents)) {
            throw ModuleDependencyException::dependentModulesExist($name->value, $enabledDependents);
        }

        // Disable the module
        $module->disable();
        $this->repository->save($module);

        // Invalidate cache
        $this->invalidateModuleCache();

        // Dispatch event
        $this->events->dispatch(new ModuleDisabled(
            $module->id()->value,
            $module->name()->value,
        ));

        return $module;
    }

    public function checkDependencies(ModuleName $name): DependencyCheckResultDTO
    {
        $module = $this->repository->findByName($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        $allModules = $this->repository->all()->all();

        // Get required modules from both requirements and dependencies
        $requiredModules = $module->requirements()->requiredModules();
        $simpleDependencies = $module->dependencies();

        // Merge both dependency sources
        $allRequirements = array_unique(array_merge($requiredModules, $simpleDependencies));

        if (empty($allRequirements)) {
            return new DependencyCheckResultDTO(satisfied: true);
        }

        $missing = [];
        $versionMismatch = [];

        // Build a map of available modules
        $availableModules = [];
        foreach ($allModules as $m) {
            $availableModules[$m->name()->value] = $m;
        }

        foreach ($allRequirements as $requirement) {
            $moduleName = $this->extractModuleName($requirement);
            $versionConstraint = $this->extractVersionConstraint($requirement);

            if (! isset($availableModules[$moduleName])) {
                $missing[] = $moduleName;

                continue;
            }

            // Check if module is enabled
            $requiredModule = $availableModules[$moduleName];
            if (! $requiredModule->isEnabled()) {
                $missing[] = $moduleName;

                continue;
            }

            // Check version constraint if specified
            if ($versionConstraint !== null) {
                if (! $requiredModule->version()->satisfies($versionConstraint)) {
                    $versionMismatch[$moduleName] = [
                        'required' => $versionConstraint,
                        'current' => $requiredModule->version()->value(),
                    ];
                }
            }
        }

        $satisfied = empty($missing) && empty($versionMismatch);

        return new DependencyCheckResultDTO(
            satisfied: $satisfied,
            missing: $missing,
            versionMismatch: $versionMismatch,
        );
    }

    public function all(): ModuleCollection
    {
        return $this->repository->all();
    }

    public function enabled(): ModuleCollection
    {
        return $this->repository->enabled();
    }

    public function find(ModuleName $name): ?Module
    {
        return $this->repository->findByName($name);
    }

    public function migrate(ModuleName $name): int
    {
        $module = $this->repository->findByName($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        return $this->migrationRunner->run($module);
    }

    public function rollback(ModuleName $name, int $steps = 1): int
    {
        $module = $this->repository->findByName($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        return $this->migrationRunner->rollback($module, $steps);
    }

    public function uninstall(ModuleName $name, bool $deleteData = false): void
    {
        $module = $this->repository->findByName($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        // Check if other enabled modules depend on this one
        $dependents = $this->getDependents($name);
        $enabledDependents = [];

        foreach ($dependents->all() as $dependent) {
            if ($dependent->isEnabled()) {
                $enabledDependents[] = $dependent->name()->value;
            }
        }

        if (! empty($enabledDependents)) {
            throw ModuleCannotUninstallException::hasDependents($name->value, $enabledDependents);
        }

        // Disable if enabled
        if ($module->isEnabled()) {
            $module->disable();
            $this->repository->save($module);
        }

        // Rollback migrations only if deleteData is true
        if ($deleteData) {
            try {
                $this->migrationRunner->rollbackAll($module);
            } catch (\Throwable $e) {
                Log::warning("Failed to rollback migrations for module {$name->value}: {$e->getMessage()}");
            }
        }

        // Store version for event before deleting
        $version = $module->version()->value();

        // Delete module files
        $modulePath = $module->path();
        if (File::isDirectory($modulePath)) {
            if (! File::deleteDirectory($modulePath)) {
                throw ModuleCannotUninstallException::deletionFailed($name->value, 'Failed to delete module directory');
            }
        }

        // Delete from database
        $this->repository->delete($module);

        // Invalidate cache
        $this->invalidateModuleCache();

        // Dispatch event
        $this->events->dispatch(new ModuleUninstalled(
            $name->value,
            $version,
        ));
    }

    public function getSettings(ModuleName $name): array
    {
        $module = $this->repository->findByName($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        $settingsKey = "modules.settings.{$name->value}";

        return config($settingsKey, []);
    }

    public function updateSettings(ModuleName $name, array $settings): void
    {
        $module = $this->repository->findByName($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        $settingsPath = $module->path().'/config/settings.php';

        // Store settings in the module's config directory
        if (! File::isDirectory(dirname($settingsPath))) {
            File::makeDirectory(dirname($settingsPath), 0755, true);
        }

        $content = "<?php\n\nreturn ".var_export($settings, true).";\n";
        File::put($settingsPath, $content);

        // Update config in memory immediately so changes are visible without restart
        config()->set("modules.settings.{$name->value}", $settings);

        // Invalidate opcache so the file is reloaded on next request
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($settingsPath, true);
        }
    }

    public function getDependents(ModuleName $name): ModuleCollection
    {
        $module = $this->repository->findByName($name);

        if ($module === null) {
            throw ModuleNotFoundException::withName($name->value);
        }

        $allModules = $this->repository->all()->all();

        return new ModuleCollection(
            ...$this->dependencyResolver->getDependents($module, $allModules)
        );
    }

    public function findByName(string $name): ?Module
    {
        return $this->repository->findByName(new ModuleName($name));
    }

    /**
     * Invalidate the module cache.
     */
    private function invalidateModuleCache(): void
    {
        if (config('modules.cache.enabled', false)) {
            Cache::forget(config('modules.cache.key', 'modules.enabled'));
        }
    }

    /**
     * Parse requirements from manifest format to ModuleRequirements.
     *
     * @param  array<string, mixed>|null  $requires
     */
    private function parseRequirements(?array $requires): ModuleRequirements
    {
        if ($requires === null) {
            return ModuleRequirements::fromArray([]);
        }

        return ModuleRequirements::fromArray([
            'php_version' => $requires['php'] ?? null,
            'laravel_version' => $requires['laravel'] ?? null,
            'required_modules' => $requires['modules'] ?? [],
            'required_extensions' => $requires['extensions'] ?? [],
        ]);
    }

    /**
     * Convert kebab-case to StudlyCase.
     */
    private function studlyCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $value)));
    }

    /**
     * Extract module name from a requirement string.
     * Format: "module-name" or "module-name:^1.0"
     */
    private function extractModuleName(string $requirement): string
    {
        $parts = explode(':', $requirement);

        return $parts[0];
    }

    /**
     * Extract version constraint from a requirement string.
     * Returns null if no constraint specified.
     */
    private function extractVersionConstraint(string $requirement): ?string
    {
        $parts = explode(':', $requirement);

        return $parts[1] ?? null;
    }

    /**
     * Get the first missing dependency from the check result.
     */
    private function getFirstMissingDependency(DependencyCheckResultDTO $result): string
    {
        if (! empty($result->missing)) {
            return $result->missing[0];
        }

        if (! empty($result->versionMismatch)) {
            return array_key_first($result->versionMismatch);
        }

        return 'unknown';
    }
}
