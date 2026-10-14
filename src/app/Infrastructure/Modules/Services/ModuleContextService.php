<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleContextServiceInterface;
use App\Modules\ModuleLoader;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ModuleContextService implements ModuleContextServiceInterface
{
    private ?string $currentModule = null;

    public function __construct(
        private readonly ModuleLoader $loader,
        private readonly ConfigRepository $config,
        private readonly ViewFactory $viewFactory,
        private readonly Translator $translator,
        private readonly UrlGenerator $urlGenerator,
    ) {}

    public function current(): ?string
    {
        return $this->currentModule;
    }

    public function setCurrent(string $moduleName): void
    {
        $this->currentModule = $moduleName;
    }

    public function clearCurrent(): void
    {
        $this->currentModule = null;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $module = $this->requireCurrentModule();

        return $this->moduleConfig($module, $key, $default);
    }

    public function path(string $path = ''): string
    {
        $module = $this->requireCurrentModule();

        return $this->modulePath($module, $path);
    }

    public function asset(string $path): string
    {
        $module = $this->requireCurrentModule();

        return $this->moduleAsset($module, $path);
    }

    public function route(string $name, array $parameters = []): string
    {
        $module = $this->requireCurrentModule();

        return $this->moduleRoute($module, $name, $parameters);
    }

    public function trans(string $key, array $replace = []): string
    {
        $module = $this->requireCurrentModule();

        return $this->moduleTrans($module, $key, $replace);
    }

    public function view(string $name, array $data = []): View
    {
        $module = $this->requireCurrentModule();

        return $this->moduleView($module, $name, $data);
    }

    public function isEnabled(string $moduleName): bool
    {
        return $this->loader->isLoaded($moduleName);
    }

    public function getEnabled(): array
    {
        return $this->loader->loadedModules();
    }

    /**
     * Read config/module.php, which ModuleServiceProvider::register() loads into
     * "modules.<name>"; falls back to the former "<snake_name>" key that modules
     * merged their config into with mergeConfigFrom().
     */
    public function moduleConfig(string $moduleName, string $key, mixed $default = null): mixed
    {
        $configKey = "modules.{$moduleName}.{$key}";

        if ($this->config->has($configKey)) {
            return $this->config->get($configKey);
        }

        return $this->config->get($this->legacyNamespace($moduleName).'.'.$key, $default);
    }

    public function modulePath(string $moduleName, string $path = ''): string
    {
        $modulesPath = $this->config->get('modules.path', base_path('modules'));
        $basePath = $modulesPath.'/'.$moduleName;

        return $path !== '' ? $basePath.'/'.ltrim($path, '/') : $basePath;
    }

    /**
     * Get an asset URL for a specific module.
     */
    private function moduleAsset(string $moduleName, string $path): string
    {
        $assetPath = 'modules/'.$moduleName.'/'.ltrim($path, '/');

        return $this->urlGenerator->asset($assetPath);
    }

    /**
     * Get a route URL for a specific module.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function moduleRoute(string $moduleName, string $name, array $parameters = []): string
    {
        $routeName = Str::snake(Str::studly($moduleName)).'.'.$name;

        return $this->urlGenerator->route($routeName, $parameters);
    }

    /**
     * Get a translation for a specific module.
     *
     * @param  array<string, mixed>  $replace
     */
    private function moduleTrans(string $moduleName, string $key, array $replace = []): string
    {
        // ModuleServiceProvider::loadTranslations() registers the kebab-case module name
        $transKey = $moduleName.'::'.$key;
        $legacyKey = $this->legacyNamespace($moduleName).'::'.$key;

        if ($legacyKey !== $transKey && ! $this->translator->has($transKey) && $this->translator->has($legacyKey)) {
            $transKey = $legacyKey;
        }

        $translation = $this->translator->get($transKey, $replace);

        return is_string($translation) ? $translation : $transKey;
    }

    /**
     * Get a view for a specific module.
     *
     * @param  array<string, mixed>  $data
     */
    private function moduleView(string $moduleName, string $name, array $data = []): View
    {
        // ModuleServiceProvider::loadViews() registers the kebab-case module name
        $viewName = $moduleName.'::'.$name;
        $legacyName = $this->legacyNamespace($moduleName).'::'.$name;

        if (! $this->viewFactory->exists($viewName) && $this->viewFactory->exists($legacyName)) {
            $viewName = $legacyName;
        }

        $this->ensureViewExists($viewName);

        return $this->viewFactory->make($viewName, $data);
    }

    /**
     * Module view names are built at runtime, so check them before rendering.
     *
     * @phpstan-assert view-string $viewName
     *
     * @throws InvalidArgumentException
     */
    private function ensureViewExists(string $viewName): void
    {
        if (! $this->viewFactory->exists($viewName)) {
            throw new InvalidArgumentException("View [{$viewName}] not found.");
        }
    }

    /**
     * The snake_case namespace these helpers used before they followed the provider.
     */
    private function legacyNamespace(string $moduleName): string
    {
        return Str::snake(Str::studly($moduleName));
    }

    /**
     * Get the current module name or throw an exception.
     *
     * @throws \RuntimeException
     */
    private function requireCurrentModule(): string
    {
        if ($this->currentModule === null) {
            throw new \RuntimeException('No module context set. Call setCurrent() first or use module-specific methods.');
        }

        return $this->currentModule;
    }
}
