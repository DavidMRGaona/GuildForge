<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Exceptions\ModuleCircularDependencyException;
use App\Domain\Modules\ValueObjects\ModuleVersion;

final readonly class ModuleDependencyResolver
{
    /**
     * Checks if all dependencies for a module are satisfied.
     *
     * @param Module $module The module to check
     * @param array<Module> $availableModules The available modules
     */
    public function areDependenciesSatisfied(Module $module, array $availableModules): bool
    {
        $requiredModules = $module->requirements()->requiredModules();

        if (empty($requiredModules)) {
            return true;
        }

        foreach ($requiredModules as $requirement) {
            if (!$this->isRequirementSatisfied($requirement, $availableModules)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Detects circular dependencies among modules.
     *
     * @param array<Module> $modules The modules to check
     * @throws ModuleCircularDependencyException When circular dependencies are found
     */
    public function detectCircularDependencies(array $modules): void
    {
        $moduleMap = $this->buildModuleMap($modules);

        foreach ($modules as $module) {
            $visited = [];
            $recursionStack = [];
            $path = [];

            $this->dfs(
                $module->name()->value,
                $moduleMap,
                $visited,
                $recursionStack,
                $path
            );
        }
    }

    /**
     * Sorts modules by their dependencies (topological sort).
     *
     * @param array<Module> $modules The modules to sort
     * @return array<Module> Modules sorted by dependencies
     * @throws ModuleCircularDependencyException When circular dependencies are found
     */
    public function sortByDependencies(array $modules): array
    {
        $this->detectCircularDependencies($modules);

        $moduleMap = $this->buildModuleMap($modules);
        $sorted = [];
        $visited = [];

        foreach ($modules as $module) {
            $this->topologicalSort($module->name()->value, $moduleMap, $visited, $sorted);
        }

        return $sorted;
    }

    /**
     * Gets all modules that depend on the given module.
     *
     * @param Module $module The module to find dependents for
     * @param array<Module> $allModules All available modules
     * @return array<Module> Modules that depend on the given module
     */
    public function getDependents(Module $module, array $allModules): array
    {
        $dependents = [];
        $targetName = $module->name()->value;

        foreach ($allModules as $candidateModule) {
            if ($candidateModule->name()->value === $targetName) {
                continue;
            }

            // Check both the requirements.requiredModules and the simpler dependencies array
            $requiredModules = $candidateModule->requirements()->requiredModules();
            $simpleDependencies = $candidateModule->dependencies();

            // Check requirements first
            foreach ($requiredModules as $requirement) {
                $requirementName = $this->extractModuleName($requirement);

                if ($requirementName === $targetName) {
                    $dependents[] = $candidateModule;
                    continue 2; // Move to next candidate
                }
            }

            // Check simple dependencies array
            if (in_array($targetName, $simpleDependencies, true)) {
                $dependents[] = $candidateModule;
            }
        }

        return $dependents;
    }

    /**
     * Validates system requirements for a module.
     *
     * @param Module $module The module to validate
     * @param string $phpVersion Current PHP version
     * @param string $laravelVersion Current Laravel version
     * @param array<string> $availableExtensions Available PHP extensions
     */
    public function validateSystemRequirements(
        Module $module,
        string $phpVersion,
        string $laravelVersion,
        array $availableExtensions,
    ): bool {
        $requirements = $module->requirements();

        // Check PHP version requirement
        $requiredPhp = $requirements->phpVersion();
        if ($requiredPhp !== null && !$this->versionSatisfiesConstraint($phpVersion, $requiredPhp)) {
            return false;
        }

        // Check Laravel version requirement
        $requiredLaravel = $requirements->laravelVersion();
        if ($requiredLaravel !== null && !$this->versionSatisfiesConstraint($laravelVersion, $requiredLaravel)) {
            return false;
        }

        // Check required extensions
        foreach ($requirements->requiredExtensions() as $extension) {
            if (!in_array($extension, $availableExtensions, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a single requirement is satisfied.
     *
     * @param array<Module> $availableModules
     */
    private function isRequirementSatisfied(string $requirement, array $availableModules): bool
    {
        $moduleName = $this->extractModuleName($requirement);
        $versionConstraint = $this->extractVersionConstraint($requirement);

        foreach ($availableModules as $available) {
            if ($available->name()->value === $moduleName) {
                if ($versionConstraint === null) {
                    return true;
                }

                return $available->version()->satisfies($versionConstraint);
            }
        }

        return false;
    }

    /**
     * Extracts module name from a requirement string.
     * Format: "module-name" or "module-name:^1.0"
     */
    private function extractModuleName(string $requirement): string
    {
        $parts = explode(':', $requirement);

        return $parts[0];
    }

    /**
     * Extracts version constraint from a requirement string.
     * Returns null if no constraint specified.
     */
    private function extractVersionConstraint(string $requirement): ?string
    {
        $parts = explode(':', $requirement);

        return $parts[1] ?? null;
    }

    /**
     * Builds a map of module name to module object.
     *
     * @param array<Module> $modules
     * @return array<string, Module>
     */
    private function buildModuleMap(array $modules): array
    {
        $map = [];
        foreach ($modules as $module) {
            $map[$module->name()->value] = $module;
        }

        return $map;
    }

    /**
     * Depth-first search for cycle detection.
     *
     * @param array<string, Module> $moduleMap
     * @param array<string, bool> $visited
     * @param array<string, bool> $recursionStack
     * @param array<string> $path
     * @throws ModuleCircularDependencyException
     */
    private function dfs(
        string $current,
        array $moduleMap,
        array &$visited,
        array &$recursionStack,
        array $path,
    ): void {
        if (isset($recursionStack[$current])) {
            // Found a cycle
            $cycleStart = array_search($current, $path, true);
            if ($cycleStart !== false) {
                $cycle = array_slice($path, (int) $cycleStart);
                throw ModuleCircularDependencyException::detected($cycle);
            }
            throw ModuleCircularDependencyException::detected([$current]);
        }

        if (isset($visited[$current])) {
            return;
        }

        $visited[$current] = true;
        $recursionStack[$current] = true;
        $path[] = $current;

        if (!isset($moduleMap[$current])) {
            unset($recursionStack[$current]);
            return;
        }

        $module = $moduleMap[$current];
        $requiredModules = $module->requirements()->requiredModules();

        foreach ($requiredModules as $requirement) {
            $depName = $this->extractModuleName($requirement);
            $this->dfs($depName, $moduleMap, $visited, $recursionStack, $path);
        }

        unset($recursionStack[$current]);
    }

    /**
     * Topological sort helper using DFS.
     *
     * @param array<string, Module> $moduleMap
     * @param array<string, bool> $visited
     * @param array<Module> $sorted
     */
    private function topologicalSort(
        string $name,
        array $moduleMap,
        array &$visited,
        array &$sorted,
    ): void {
        if (isset($visited[$name])) {
            return;
        }

        $visited[$name] = true;

        if (!isset($moduleMap[$name])) {
            return;
        }

        $module = $moduleMap[$name];
        $requiredModules = $module->requirements()->requiredModules();

        foreach ($requiredModules as $requirement) {
            $depName = $this->extractModuleName($requirement);
            $this->topologicalSort($depName, $moduleMap, $visited, $sorted);
        }

        $sorted[] = $module;
    }

    /**
     * Checks if a version satisfies a constraint.
     */
    private function versionSatisfiesConstraint(string $version, string $constraint): bool
    {
        $normalizedVersion = $this->normalizeVersion($version);

        try {
            $moduleVersion = ModuleVersion::fromString($normalizedVersion);

            return $moduleVersion->satisfies($constraint);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Normalizes a version string to semver format.
     */
    private function normalizeVersion(string $version): string
    {
        // Extract just the version numbers (e.g., "8.3.0" from "8.3.0-dev")
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches)) {
            return "{$matches[1]}.{$matches[2]}.{$matches[3]}";
        }

        // Handle two-part versions (e.g., "8.3" -> "8.3.0")
        if (preg_match('/^(\d+)\.(\d+)$/', $version, $matches)) {
            return "{$matches[1]}.{$matches[2]}.0";
        }

        return $version;
    }
}
