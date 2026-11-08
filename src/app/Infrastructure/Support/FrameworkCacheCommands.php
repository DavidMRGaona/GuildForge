<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

/**
 * Artisan commands module:refresh-caches runs to drop compiled files outright.
 *
 * Only Blade icons and Filament components: the deploy never builds those caches, so
 * these normally find nothing to delete. The config, route and event caches are rebuilt
 * in place (AtomicFrameworkCacheRebuilder) and compiled views recompiled over the old
 * ones (view:recompile), never cleared first: clearing leaves a gap in which a request
 * fails to load them (core#408). clear-compiled is left out for the same reason:
 * services.php and packages.php do not depend on modules.
 *
 * Never use cache:clear or optimize:clear for this: with the Redis store they run FLUSHDB
 * on the cache database, which production shares between tenants, so one tenant would
 * drop the other's cache entries and queue:restart signal. Cache locks live in Redis
 * database 0 (lock_connection is "default"), not in the cache database.
 */
final class FrameworkCacheCommands
{
    /** @var list<string> */
    public const array CLEAR = [
        'icons:clear',
        'filament:optimize-clear',
    ];
}
