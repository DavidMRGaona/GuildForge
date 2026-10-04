<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use Illuminate\Console\Command;

/**
 * Rebuild the caches a module update may invalidate (config, routes, views).
 *
 * Run by the updater in a fresh process once the update is committed: rebuilding
 * the config cache inside the queue worker replaces its application instance.
 * Caches are only rebuilt when the site was using them in the first place.
 */
final class RefreshCachesCommand extends Command
{
    protected $signature = 'module:refresh-caches';

    protected $description = 'Clear and rebuild the application caches after a module update (used by the updater)';

    protected $hidden = true;

    public function handle(): int
    {
        if ($this->laravel->runningUnitTests()) {
            return self::SUCCESS;
        }

        $configCached = $this->laravel->configurationIsCached();
        $routesCached = $this->laravel->routesAreCached();

        $this->callSilently('optimize:clear');

        if ($configCached) {
            $this->callSilently('config:cache');
            $this->callSilently('view:cache');
        }

        if ($routesCached) {
            $this->callSilently('route:cache');
        }

        return self::SUCCESS;
    }
}
