<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Application\Modules\DTOs\PagePrefixDTO;

interface ModulePageRegistryInterface
{
    /**
     * Register a page prefix from a module.
     */
    public function register(PagePrefixDTO $dto): void;

    /**
     * Register multiple page prefixes from a module.
     *
     * @param  array<PagePrefixDTO>  $prefixes
     */
    public function registerMany(array $prefixes): void;

    /**
     * Get all registered page prefixes.
     *
     * @return array<PagePrefixDTO>
     */
    public function all(): array;

    /**
     * Get the module name for a given prefix.
     * Returns null if the prefix is not registered.
     */
    public function getModuleForPrefix(string $prefix): ?string;

    /**
     * Convert registry to Inertia payload format.
     * Returns a map of prefix => module.
     *
     * @return array<string, string>
     */
    public function toInertiaPayload(): array;

    /**
     * Unregister all page prefixes for a module.
     */
    public function unregisterModule(string $moduleName): void;

    /**
     * Clear all registered page prefixes.
     */
    public function clear(): void;
}
