<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Listeners;

use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Clears Laravel caches when a module is enabled or disabled.
 *
 * Required in production where routes are cached. Without clearing the cache,
 * Filament resources from newly enabled modules won't have their routes
 * registered, causing "Route not defined" errors.
 */
final class ClearCachesOnModuleChange
{
    public function handle(ModuleEnabled|ModuleDisabled $event): void
    {
        try {
            // Route cache must be cleared for new Filament resources to work
            Artisan::call('route:clear');

            // View cache should also be cleared for module views
            Artisan::call('view:clear');
        } catch (\Throwable $e) {
            Log::warning('Failed to clear Laravel caches after module change: '.$e->getMessage(), [
                'module' => $event->moduleName,
                'event' => $event::class,
            ]);
        }
    }
}
