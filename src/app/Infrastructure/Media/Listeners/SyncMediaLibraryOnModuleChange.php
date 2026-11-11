<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Listeners;

use App\Infrastructure\Media\Jobs\SyncMediaLibraryJob;

/**
 * Handles every module lifecycle event that can add, change or take away media sources. Without it, the
 * files of a module just installed show as unused until the nightly sync (core#517). Uses of a module no
 * longer loaded are kept by the sync, so they still block deleting its files.
 */
final class SyncMediaLibraryOnModuleChange
{
    public function handle(object $event): void
    {
        SyncMediaLibraryJob::dispatchSafely();
    }
}
