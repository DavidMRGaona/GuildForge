<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

/**
 * The authorization cache version tokens as read by the current request (or queue job).
 *
 * Bound as a scoped instance: Laravel drops it when a new request or job starts, so a token
 * is read at most once per lifecycle and never carried over by a long-running worker.
 */
final class AuthorizationCacheVersion
{
    public ?string $token = null;

    /** @var array<int|string, string> Each user's own token, by user id */
    public array $userTokens = [];
}
