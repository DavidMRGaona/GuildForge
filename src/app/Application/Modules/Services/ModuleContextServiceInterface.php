<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use Illuminate\Contracts\View\View;

interface ModuleContextServiceInterface
{
    /**
     * Get the current module name.
     */
    public function current(): ?string;

    /**
     * Set the current module context.
     */
    public function setCurrent(string $moduleName): void;

    /**
     * Clear the current module context.
     */
    public function clearCurrent(): void;

    /**
     * Get a configuration value for a module.
     */
    public function config(string $key, mixed $default = null): mixed;

    /**
     * Get the path for the current or specified module.
     */
    public function path(string $path = ''): string;

    /**
     * Get an asset URL for the current or specified module.
     */
    public function asset(string $path): string;

    /**
     * Get a route URL for the current or specified module.
     *
     * @param array<string, mixed> $parameters
     */
    public function route(string $name, array $parameters = []): string;

    /**
     * Get a translation for the current or specified module.
     *
     * @param array<string, mixed> $replace
     */
    public function trans(string $key, array $replace = []): string;

    /**
     * Get a view for the current or specified module.
     *
     * @param array<string, mixed> $data
     */
    public function view(string $name, array $data = []): View;

    /**
     * Check if a module is enabled.
     */
    public function isEnabled(string $moduleName): bool;

    /**
     * Get all enabled modules.
     *
     * @return array<string>
     */
    public function getEnabled(): array;

    /**
     * Get configuration value for a specific module.
     */
    public function moduleConfig(string $moduleName, string $key, mixed $default = null): mixed;

    /**
     * Get path for a specific module.
     */
    public function modulePath(string $moduleName, string $path = ''): string;
}
