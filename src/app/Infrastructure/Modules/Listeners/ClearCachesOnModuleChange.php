<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Listeners;

use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleInstalled;
use App\Domain\Modules\Events\ModuleUpdated;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Clears and rebuilds Laravel caches when a module is enabled, disabled, installed, or updated.
 *
 * Required in production where routes are cached. Without clearing and
 * rebuilding the cache, Filament resources from newly enabled modules
 * won't have their routes registered, causing "Route not defined" errors.
 */
final class ClearCachesOnModuleChange
{
    public function handle(ModuleEnabled|ModuleDisabled|ModuleInstalled|ModuleUpdated $event): void
    {
        // In tests, caches (routes, views, config) are not active, so clearing
        // and rebuilding is unnecessary and destroys compiled Blade views
        // mid-request, crashing subsequent Livewire re-renders.
        if (app()->runningUnitTests()) {
            return;
        }

        try {
            // 1. Clear all Laravel caches (config, routes, views, events, compiled)
            Artisan::call('optimize:clear');

            // 2. Clear Filament component discovery cache
            $filamentCachePath = config('filament.cache_path', base_path('bootstrap/cache/filament'));
            if (is_dir($filamentCachePath)) {
                File::deleteDirectory($filamentCachePath);
            }

            // 3. Invalidate OPcache for module files
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            // 4. Rebuild caches
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
        } catch (\Throwable $e) {
            Log::warning('Failed to rebuild caches after module change', [
                'module' => $event->moduleName,
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
