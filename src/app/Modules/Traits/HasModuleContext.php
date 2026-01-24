<?php

declare(strict_types=1);

namespace App\Modules\Traits;

use App\Application\Modules\Services\ModuleContextServiceInterface;
use Illuminate\Contracts\View\View;

/**
 * Trait for classes that belong to a specific module.
 *
 * Use this trait in service providers, controllers, or other classes
 * that need easy access to module-specific resources.
 */
trait HasModuleContext
{
    protected ?string $moduleName = null;

    /**
     * Get the module context service.
     */
    protected function moduleContext(): ModuleContextServiceInterface
    {
        /** @var ModuleContextServiceInterface $service */
        $service = app(ModuleContextServiceInterface::class);

        if ($this->moduleName !== null) {
            $service->setCurrent($this->moduleName);
        }

        return $service;
    }

    /**
     * Set the module name for this instance.
     */
    protected function setModuleName(string $moduleName): void
    {
        $this->moduleName = $moduleName;
    }

    /**
     * Get a configuration value for this module.
     */
    protected function moduleConfig(string $key, mixed $default = null): mixed
    {
        return $this->moduleContext()->config($key, $default);
    }

    /**
     * Get a path within this module.
     */
    protected function modulePath(string $path = ''): string
    {
        return $this->moduleContext()->path($path);
    }

    /**
     * Get an asset URL for this module.
     */
    protected function moduleAsset(string $path): string
    {
        return $this->moduleContext()->asset($path);
    }

    /**
     * Get a route URL for this module.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function moduleRoute(string $name, array $parameters = []): string
    {
        return $this->moduleContext()->route($name, $parameters);
    }

    /**
     * Get a translation for this module.
     *
     * @param  array<string, mixed>  $replace
     */
    protected function moduleTrans(string $key, array $replace = []): string
    {
        return $this->moduleContext()->trans($key, $replace);
    }

    /**
     * Get a view for this module.
     *
     * @param  array<string, mixed>  $data
     */
    protected function moduleView(string $name, array $data = []): View
    {
        return $this->moduleContext()->view($name, $data);
    }
}
