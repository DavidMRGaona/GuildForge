<?php

declare(strict_types=1);

use App\Application\Modules\Services\ModuleContextServiceInterface;

if (! function_exists('module')) {
    /**
     * Get the module context service or set the current module.
     *
     * @return ModuleContextServiceInterface|string|null
     */
    function module(?string $name = null): ModuleContextServiceInterface|string|null
    {
        /** @var ModuleContextServiceInterface $service */
        $service = app(ModuleContextServiceInterface::class);

        if ($name === null) {
            return $service;
        }

        $service->setCurrent($name);

        return $service->current();
    }
}

if (! function_exists('module_path')) {
    /**
     * Get the path to a module directory.
     */
    function module_path(string $module, string $path = ''): string
    {
        /** @var ModuleContextServiceInterface $service */
        $service = app(ModuleContextServiceInterface::class);

        return $service->modulePath($module, $path);
    }
}

if (! function_exists('module_config')) {
    /**
     * Get a module configuration value.
     */
    function module_config(string $module, string $key, mixed $default = null): mixed
    {
        /** @var ModuleContextServiceInterface $service */
        $service = app(ModuleContextServiceInterface::class);

        return $service->moduleConfig($module, $key, $default);
    }
}

if (! function_exists('module_trans')) {
    /**
     * Get a module translation.
     *
     * @param array<string, mixed> $replace
     */
    function module_trans(string $module, string $key, array $replace = []): string
    {
        /** @var ModuleContextServiceInterface $service */
        $service = app(ModuleContextServiceInterface::class);

        $service->setCurrent($module);

        return $service->trans($key, $replace);
    }
}

if (! function_exists('module_asset')) {
    /**
     * Get a module asset URL.
     */
    function module_asset(string $module, string $path): string
    {
        /** @var ModuleContextServiceInterface $service */
        $service = app(ModuleContextServiceInterface::class);

        $service->setCurrent($module);

        return $service->asset($path);
    }
}

if (! function_exists('module_route')) {
    /**
     * Get a module route URL.
     *
     * @param array<string, mixed> $parameters
     */
    function module_route(string $module, string $name, array $parameters = []): string
    {
        /** @var ModuleContextServiceInterface $service */
        $service = app(ModuleContextServiceInterface::class);

        $service->setCurrent($module);

        return $service->route($name, $parameters);
    }
}

if (! function_exists('module_enabled')) {
    /**
     * Check if a module is enabled.
     */
    function module_enabled(string $module): bool
    {
        /** @var ModuleContextServiceInterface $service */
        $service = app(ModuleContextServiceInterface::class);

        return $service->isEnabled($module);
    }
}
