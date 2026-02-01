<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Jobs;

use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\Domain\Updates\Events\UpdatesAvailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class CheckModuleUpdatesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct()
    {
    }

    public function handle(
        ModuleUpdateCheckerInterface $updateChecker,
        Dispatcher $events,
    ): void {
        if (! config('updates.enabled', true)) {
            return;
        }

        try {
            $availableUpdates = $updateChecker->checkAllForUpdates();

            if ($availableUpdates->isEmpty()) {
                Log::info('Module update check completed: No updates available');

                return;
            }

            Log::info('Module update check completed', [
                'updates_available' => $availableUpdates->count(),
                'modules' => $availableUpdates->pluck('moduleName')->toArray(),
            ]);

            // Transform to the format expected by the event
            $moduleUpdates = [];
            foreach ($availableUpdates as $update) {
                $moduleUpdates[$update->moduleName] = [
                    'current' => $update->currentVersion,
                    'available' => $update->availableVersion,
                ];
            }

            // Dispatch event for notification handling
            $events->dispatch(new UpdatesAvailable($moduleUpdates));
        } catch (\Throwable $e) {
            Log::error('Module update check failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function tags(): array
    {
        return ['updates', 'module-updates'];
    }
}
