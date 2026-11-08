<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Listeners;

use App\Application\Updates\Services\ModulePostUpdateRunnerInterface;
use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Domain\Modules\Events\ModuleInstalled;
use App\Domain\Modules\Events\ModuleUpdated;

/**
 * Refreshes the framework caches when a module is enabled, disabled, installed, or updated.
 *
 * With cached routes or config, the cache must follow the module change, or the module's
 * Filament resources are missing from it ("Route not defined"). The refresh runs in a fresh
 * process (module:refresh-caches): it rebuilds in place only the caches in use, without the
 * moment in which a request finds them missing (core#408), and without replacing the
 * application instance of this request or worker.
 */
final readonly class ClearCachesOnModuleChange
{
    public function __construct(
        private ModulePostUpdateRunnerInterface $runner,
    ) {}

    public function handle(ModuleEnabled|ModuleDisabled|ModuleInstalled|ModuleUpdated $event): void
    {
        // In tests, caches (routes, views, config) are not active, so refreshing them is
        // unnecessary and rewriting compiled Blade views mid-request breaks Livewire re-renders
        if (app()->runningUnitTests()) {
            return;
        }

        // Module PHP files changed; PHP-FPM revalidates them by timestamp anyway, this only
        // makes it immediate when the change came through a web request
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        // Best effort: failures are logged by the runner, never thrown
        $this->runner->refreshCaches();
    }
}
