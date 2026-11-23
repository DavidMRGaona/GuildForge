<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Infrastructure\Media\Services\MediaSourceSyncState;

/**
 * Tests that write index rows by hand instead of syncing: the library shows a source's images only while
 * the index is up to date with it (core#837).
 */
trait MarksMediaSourcesSynced
{
    protected function markMediaSourcesSynced(): void
    {
        $state = $this->app->make(MediaSourceSyncState::class);
        $state->record($state->fingerprints($this->app->make(MediaSourceRegistryInterface::class)->sources()), []);
    }
}
