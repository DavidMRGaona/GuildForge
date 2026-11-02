<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Listeners;

use App\Infrastructure\Knowledge\Jobs\SyncKnowledgeCatalogJob;

/**
 * Handles every module lifecycle event that can add, change or remove guide files.
 */
final class SyncKnowledgeOnModuleChange
{
    public function handle(object $event): void
    {
        SyncKnowledgeCatalogJob::dispatchSafely();
    }
}
