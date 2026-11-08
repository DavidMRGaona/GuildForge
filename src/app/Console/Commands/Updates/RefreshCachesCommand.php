<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Infrastructure\Support\AtomicFrameworkCacheRebuilder;
use App\Infrastructure\Support\FrameworkCache;
use App\Infrastructure\Support\FrameworkCacheCommands;
use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;

/**
 * Refresh the framework caches a module change may invalidate (config, routes, events, views).
 *
 * Run in a fresh process, by the updater once the update is committed and on every module
 * enable, disable or install: rebuilding the config cache inside the queue worker or a web
 * request replaces its application instance. Only the caches the site was using are
 * rebuilt, and each one in place while the site keeps serving requests (core#408): a cache
 * that was not in use stays that way.
 *
 * The data cache is never cleared: with Redis that is a FLUSHDB of a database the
 * production tenants share (see FrameworkCacheCommands).
 */
final class RefreshCachesCommand extends Command
{
    protected $signature = 'module:refresh-caches';

    protected $description = 'Rebuild in place the framework caches in use after a module change (used by the updater)';

    protected $hidden = true;

    public function __construct(
        private readonly AtomicFrameworkCacheRebuilder $rebuilder,
    ) {
        parent::__construct();
    }

    public function handle(Application $app): int
    {
        if ($app->runningUnitTests()) {
            return self::SUCCESS;
        }

        $this->refresh(array_values(array_filter(
            FrameworkCache::cases(),
            fn (FrameworkCache $cache): bool => $cache->isCached($app),
        )));

        return self::SUCCESS;
    }

    /**
     * Rebuild the caches in use and refresh the compiled views and discovery caches.
     *
     * @param  list<FrameworkCache>  $cached  The caches the site was using
     * @return list<string> The cache commands and Artisan commands run, in order
     */
    public function refresh(array $cached): array
    {
        $ran = [];

        // In case order: the config first, since the other children boot with it
        foreach (FrameworkCache::cases() as $cache) {
            if (in_array($cache, $cached, true)) {
                $this->rebuilder->rebuild($cache);
                $ran[] = $cache->cacheCommand();
            }
        }

        // A cached config stands for a production-like site, where views are precompiled;
        // elsewhere they are compiled on demand, so dropping them is enough
        $commands = [
            in_array(FrameworkCache::Config, $cached, true) ? 'view:recompile' : 'view:clear',
            ...FrameworkCacheCommands::CLEAR,
        ];

        foreach ($commands as $command) {
            Artisan::call($command);
            $ran[] = $command;
        }

        return $ran;
    }
}
