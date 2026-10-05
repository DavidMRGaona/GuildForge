<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

/**
 * Artisan commands that drop the framework's compiled files (compiled classes,
 * config, events, routes, views, Blade icons and Filament components) without
 * touching the data cache.
 *
 * This is optimize:clear minus cache:clear. Never use either of those two for this:
 * with the Redis store they run FLUSHDB on the cache database, which production
 * shares between tenants, so one tenant would drop the other's cache entries,
 * locks and queue:restart signal.
 */
final class FrameworkCacheCommands
{
    /** @var list<string> */
    public const array CLEAR = [
        'clear-compiled',
        'config:clear',
        'event:clear',
        'route:clear',
        'view:clear',
        'icons:clear',
        'filament:optimize-clear',
    ];
}
