<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use Illuminate\Foundation\Application;

/**
 * The framework caches written as one PHP file each, which a request loads with require.
 *
 * Laravel reads each file's path from an environment variable, so a child process can
 * build the cache at another path while the site keeps loading the current one
 * (AtomicFrameworkCacheRebuilder). Cases are in rebuild order: the children that cache
 * routes and events boot with the config cache, so it goes first.
 */
enum FrameworkCache: string
{
    case Config = 'config';
    case Routes = 'routes';
    case Events = 'events';

    public function cacheCommand(): string
    {
        return match ($this) {
            self::Config => 'config:cache',
            self::Routes => 'route:cache',
            self::Events => 'event:cache',
        };
    }

    /** Environment variable Laravel reads the file's path from (Application::normalizeCachePath()). */
    public function pathVariable(): string
    {
        return match ($this) {
            self::Config => 'APP_CONFIG_CACHE',
            self::Routes => 'APP_ROUTES_CACHE',
            self::Events => 'APP_EVENTS_CACHE',
        };
    }

    public function path(Application $app): string
    {
        return match ($this) {
            self::Config => $app->getCachedConfigPath(),
            self::Routes => $app->getCachedRoutesPath(),
            self::Events => $app->getCachedEventsPath(),
        };
    }

    public function isCached(Application $app): bool
    {
        return match ($this) {
            self::Config => $app->configurationIsCached(),
            self::Routes => $app->routesAreCached(),
            self::Events => $app->eventsAreCached(),
        };
    }
}
