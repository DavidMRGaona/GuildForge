<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Listeners;

use App\Domain\Modules\Exceptions\ModuleSettingsUnavailableException;
use App\Infrastructure\Modules\Services\ModuleSettingsConfig;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;

/**
 * Before a queue worker runs each job, brings config('modules.settings.*') up to date with the
 * values saved since the worker booted (core#825), so a job never decides on a setting changed
 * up to an hour ago in the panel.
 *
 * A sync job runs inside the request or command that dispatched it, which booted with the current
 * values and may have set some on purpose (a test, the request that saved them): it is left alone.
 *
 * When the settings cannot be read the exception reaches the worker, which counts it as a failed
 * attempt and runs the job again later, as with any job that cannot reach its database or cache.
 */
final readonly class RefreshModuleSettingsForEachJob
{
    public function __construct(
        private ModuleSettingsConfig $settings,
    ) {}

    /**
     * @throws ModuleSettingsUnavailableException
     */
    public function handle(JobProcessing $event): void
    {
        if ($event->job instanceof SyncJob) {
            return;
        }

        $this->settings->refresh();
    }
}
