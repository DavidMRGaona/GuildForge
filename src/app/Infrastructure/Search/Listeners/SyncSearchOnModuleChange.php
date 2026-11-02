<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Listeners;

use App\Infrastructure\Search\Jobs\SyncSearchIndexJob;

/**
 * Handles every module lifecycle event that can add, change or remove search document types.
 */
final class SyncSearchOnModuleChange
{
    public function handle(object $event): void
    {
        SyncSearchIndexJob::dispatchSafely();
    }
}
