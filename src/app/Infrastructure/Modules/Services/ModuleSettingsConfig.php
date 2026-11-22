<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Application\Services\SettingsVersionInterface;
use App\Domain\Modules\Exceptions\ModuleSettingsUnavailableException;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Publishes each booted module's settings under config('modules.settings.<name>') and keeps them
 * current in long-running processes (core#825).
 *
 * A module provider publishes them once, when the process boots. A web request ends soon after,
 * but a queue worker runs for up to an hour: before each of its jobs, refresh() compares the
 * settings version with the one it last published with and, when another process saved any
 * setting since, publishes every module's settings again. Unchanged, a job costs one cache read
 * (the version, which the job's own settings reads then reuse).
 *
 * One per process (a singleton, never scoped: the worker forgets scoped instances between jobs).
 * The store and the version are resolved on each call, so a binding replaced later is honoured.
 */
final class ModuleSettingsConfig
{
    /** @var array<string, string> Module name => module path, in boot order */
    private array $modules = [];

    /** The settings version the published values were read with; null until refresh() reads them */
    private ?string $publishedWith = null;

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Publish a module's settings while it boots: release defaults with the stored values on top.
     * Never throws: a store that cannot be read gives the defaults, and the first job a worker
     * runs reads them again strictly.
     */
    public function publish(string $moduleName, string $modulePath): void
    {
        $this->modules[$moduleName] = $modulePath;

        $settings = $this->store()->effective($moduleName, $modulePath);

        if ($settings !== []) {
            config()->set($this->key($moduleName), $settings);
        }
    }

    /**
     * Publish every booted module's settings again when any setting changed since the last time.
     * Fails closed: when the version or the stored values cannot be read it throws, leaving the
     * next call to read them all again, so the caller never goes on with values it cannot trust.
     *
     * @throws ModuleSettingsUnavailableException
     */
    public function refresh(): void
    {
        if ($this->modules === []) {
            return;
        }

        try {
            $version = $this->container->make(SettingsVersionInterface::class)->current();
        } catch (Throwable $e) {
            throw ModuleSettingsUnavailableException::versionUnreadable($e);
        }

        if ($version === $this->publishedWith) {
            return;
        }

        // Until every module is read again, the next call starts over
        $this->publishedWith = null;
        $store = $this->store();

        foreach ($this->modules as $moduleName => $modulePath) {
            $settings = $store->current($moduleName, $modulePath);

            // As when booting, nothing is set for a module without settings, unless it had some
            if ($settings !== [] || config()->has($this->key($moduleName))) {
                config()->set($this->key($moduleName), $settings);
            }
        }

        $this->publishedWith = $version;
    }

    private function store(): ModuleSettingsStoreInterface
    {
        return $this->container->make(ModuleSettingsStoreInterface::class);
    }

    private function key(string $moduleName): string
    {
        return "modules.settings.{$moduleName}";
    }
}
