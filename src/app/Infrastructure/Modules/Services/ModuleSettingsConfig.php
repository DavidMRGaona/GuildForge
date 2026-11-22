<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleSettingsConfigInterface;
use App\Application\Modules\Services\ModuleSettingsStoreInterface;
use App\Application\Services\SettingsVersionInterface;
use App\Domain\Modules\Exceptions\ModuleSettingsUnavailableException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * A module provider publishes its settings once, when the process boots. A web request ends soon
 * after, but a queue worker runs for up to an hour: before each of its jobs, refresh() compares
 * the settings version with the one it last published with and, when another process saved any
 * setting since, publishes every module's settings again (core#825).
 *
 * Unchanged, a job costs one read of the version from the cache store (a query on the `cache`
 * table with the database store), which the job's own settings reads then reuse. After a change,
 * the next job reads each module's stored settings once, a cost paid once per change and worker.
 *
 * One per process (a singleton, never scoped: the worker forgets scoped instances between jobs).
 * The store and the version are resolved on each call, so a binding replaced later is honoured.
 */
final class ModuleSettingsConfig implements ModuleSettingsConfigInterface
{
    /** Pause before the one retry: a store restarting or a brief network cut is usually back by then */
    public const int RETRY_AFTER_MILLISECONDS = 1500;

    /** @var array<string, string> Module name => module path, in boot order */
    private array $modules = [];

    /** The settings version the published values were read with; null until refresh() reads them */
    private ?string $publishedWith = null;

    public function __construct(
        private readonly Container $container,
    ) {}

    public function publish(string $moduleName, string $modulePath): void
    {
        $this->modules[$moduleName] = $modulePath;
        // Published from a read without a version: the next refresh() reads every module again
        $this->publishedWith = null;

        $settings = $this->store()->effective($moduleName, $modulePath);

        if ($settings !== []) {
            config()->set($this->key($moduleName), $settings);
        }
    }

    public function refresh(): void
    {
        if ($this->modules === []) {
            return;
        }

        try {
            $this->publishIfChanged();
        } catch (ModuleSettingsUnavailableException $e) {
            // A brief cut of the cache or the database must not cost every job reserved meanwhile
            // an attempt (the worker retries without waiting): try once more after a pause
            Log::warning('[Modules] Module settings could not be read before a job; trying again', [
                'exception' => ($e->getPrevious() ?? $e)::class,
            ]);
            Sleep::for(self::RETRY_AFTER_MILLISECONDS)->milliseconds();

            $this->publishIfChanged();
        }
    }

    /**
     * @throws ModuleSettingsUnavailableException
     */
    private function publishIfChanged(): void
    {
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
