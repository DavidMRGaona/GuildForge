<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Application\Activity\Services\ActivityReaderInterface;
use App\Application\Activity\Services\ActivitySourceRegistryInterface;
use App\Infrastructure\Activity\ActivityReader;
use App\Infrastructure\Activity\ActivitySourceRegistry;
use App\Infrastructure\Activity\EventActivityPublisher;
use Illuminate\Support\ServiceProvider;

/**
 * The activity contract (core#774): modules report who took part in their things, consumers read it.
 * The registry is a singleton the modules fill while they boot; the reader is scoped, so the sources
 * it builds and the warnings it remembers in memory last one request or job (the cache keeps
 * warnings apart for a day).
 */
final class ActivityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Built by hand so the cache is only asked for when warning, not at boot
        $this->app->singleton(ActivitySourceRegistryInterface::class, static fn ($app): ActivitySourceRegistry => new ActivitySourceRegistry($app));
        $this->app->scoped(ActivityReaderInterface::class, ActivityReader::class);
        // Scoped: what an open transaction already announces is known to every caller of the request or job
        $this->app->scoped(ActivityPublisherInterface::class, EventActivityPublisher::class);
    }
}
