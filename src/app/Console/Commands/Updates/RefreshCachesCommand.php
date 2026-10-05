<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Infrastructure\Support\FrameworkCacheCommands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Rebuild the caches a module update may invalidate (config, routes, events, views).
 *
 * Run by the updater in a fresh process once the update is committed: rebuilding
 * the config cache inside the queue worker replaces its application instance.
 * Caches are only rebuilt when the site was using them in the first place.
 *
 * The data cache is never cleared: with Redis that is a FLUSHDB of a database the
 * production tenants share (see FrameworkCacheCommands).
 */
final class RefreshCachesCommand extends Command
{
    protected $signature = 'module:refresh-caches';

    protected $description = 'Clear and rebuild the framework caches after a module update (used by the updater)';

    protected $hidden = true;

    public function handle(): int
    {
        if ($this->laravel->runningUnitTests()) {
            return self::SUCCESS;
        }

        $this->refresh(
            configCached: $this->laravel->configurationIsCached(),
            routesCached: $this->laravel->routesAreCached(),
        );

        return self::SUCCESS;
    }

    /**
     * Clear the framework caches and rebuild the ones that were in use.
     *
     * @return list<string> The Artisan commands run, in order
     */
    public function refresh(bool $configCached, bool $routesCached): array
    {
        $commands = FrameworkCacheCommands::CLEAR;

        if ($configCached) {
            $commands[] = 'config:cache';
            $commands[] = 'view:cache';
        }

        if ($routesCached) {
            $commands[] = 'route:cache';
        }

        foreach ($commands as $command) {
            Artisan::call($command);
        }

        return $commands;
    }
}
