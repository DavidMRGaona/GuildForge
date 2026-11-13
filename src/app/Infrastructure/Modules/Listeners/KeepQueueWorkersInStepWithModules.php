<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Listeners;

use App\Application\Modules\Services\ModuleChangeWindowInterface;
use App\Domain\Modules\ValueObjects\ModuleSetFingerprint;
use App\Modules\ModuleLoader;
use Illuminate\Contracts\Container\Container;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Worker;
use Illuminate\Support\Facades\Log;

/**
 * Keeps each queue worker on the modules the site runs now (core#624). A worker loads the module
 * classes once, at boot: a job of a module enabled or updated after that would be unserialized
 * against classes this process does not have (an incomplete object) and fail.
 *
 * Runs before the worker reserves each job (Looping); returning false makes it sleep and look
 * again without taking one:
 *  - while some process changes the modules, the worker waits; a worker started then would
 *    boot halfway through the change, so it does not stop yet;
 *  - once the change is over, a worker booted with other modules stops, and whatever supervises
 *    it starts a fresh one that loads the current modules and takes the jobs left waiting.
 *
 * When it cannot tell (the process did not boot the modules, nothing recorded the modules yet,
 * the cache is unreachable) the worker carries on as it would without this listener.
 */
final readonly class KeepQueueWorkersInStepWithModules
{
    public function __construct(
        private ModuleChangeWindowInterface $window,
        private ModuleLoader $loader,
        private Container $container,
    ) {}

    public function handle(Looping $event): ?bool
    {
        $bootedWith = $this->loader->bootedFingerprint();

        if (! $bootedWith instanceof ModuleSetFingerprint) {
            return null;
        }

        if ($this->window->inProgress()) {
            return false;
        }

        $current = $this->window->currentFingerprint();

        if (! $current instanceof ModuleSetFingerprint || $current->equals($bootedWith)) {
            return null;
        }

        $this->stopWorker($event);

        return false;
    }

    private function stopWorker(Looping $event): void
    {
        if (! $this->container->bound('queue.worker')) {
            return;
        }

        /** @var Worker $worker */
        $worker = $this->container->make('queue.worker');

        if (! $worker->shouldQuit) {
            Log::info('[Modules] The modules changed since this queue worker started; it stops to start again with them', [
                'connection' => $event->connectionName,
                'queue' => $event->queue,
            ]);
            $worker->shouldQuit = true;
        }
    }
}
