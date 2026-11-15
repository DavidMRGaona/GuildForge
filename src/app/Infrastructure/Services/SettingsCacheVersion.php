<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

/**
 * The settings cache version as read by the current request (or queue job).
 *
 * Bound as a scoped instance: Laravel drops it when a new request or job starts, so the
 * version is read at most once per lifecycle and never carried over by a long-running worker.
 */
final class SettingsCacheVersion
{
    public ?string $token = null;

    /**
     * True from a write until its commit renews the version: meanwhile this request reads the
     * table, so it sees its own write and never caches a row that may still be rolled back.
     * A rollback leaves it set until the request ends, which only costs uncached reads.
     */
    public bool $pendingWrite = false;
}
