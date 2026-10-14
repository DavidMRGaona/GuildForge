<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

/**
 * The authorization cache version token as read by the current request (or queue job).
 *
 * Bound as a scoped instance: Laravel drops it when a new request or job starts, so a token
 * is read at most once per lifecycle and never carried over by a long-running worker.
 */
final class AuthorizationCacheVersion
{
    public ?string $token = null;
}
