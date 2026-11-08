<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Support;

use App\Infrastructure\Support\FrameworkCache;
use Tests\TestCase;

final class FrameworkCacheTest extends TestCase
{
    public function test_config_cache_matches_the_framework(): void
    {
        $this->assertSame('config:cache', FrameworkCache::Config->cacheCommand());
        $this->assertSame('APP_CONFIG_CACHE', FrameworkCache::Config->pathVariable());
        $this->assertSame($this->app->getCachedConfigPath(), FrameworkCache::Config->path($this->app));
        $this->assertSame($this->app->configurationIsCached(), FrameworkCache::Config->isCached($this->app));
    }

    public function test_route_cache_matches_the_framework(): void
    {
        $this->assertSame('route:cache', FrameworkCache::Routes->cacheCommand());
        $this->assertSame('APP_ROUTES_CACHE', FrameworkCache::Routes->pathVariable());
        $this->assertSame($this->app->getCachedRoutesPath(), FrameworkCache::Routes->path($this->app));
        $this->assertSame($this->app->routesAreCached(), FrameworkCache::Routes->isCached($this->app));
    }

    public function test_event_cache_matches_the_framework(): void
    {
        $this->assertSame('event:cache', FrameworkCache::Events->cacheCommand());
        $this->assertSame('APP_EVENTS_CACHE', FrameworkCache::Events->pathVariable());
        $this->assertSame($this->app->getCachedEventsPath(), FrameworkCache::Events->path($this->app));
        $this->assertSame($this->app->eventsAreCached(), FrameworkCache::Events->isCached($this->app));
    }

    public function test_the_path_variable_moves_the_cache_file(): void
    {
        $path = sys_get_temp_dir().'/framework-cache-test-routes.php';
        $_SERVER['APP_ROUTES_CACHE'] = $path;

        try {
            $this->assertSame($path, FrameworkCache::Routes->path($this->app));
        } finally {
            unset($_SERVER['APP_ROUTES_CACHE']);
        }
    }
}
