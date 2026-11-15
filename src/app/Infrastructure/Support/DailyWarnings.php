<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Warnings logged once a day each. What is drawn on every page (the footer's blocks, a profile's
 * sections) is resolved again on every request, so something broken would otherwise log on every
 * request: each warning is remembered in the cache for a day under its prefix (and in memory, for
 * the process). Without a cache it still warns, once per process. The cache is asked for only when
 * warning, so building whatever holds it touches nothing.
 */
final class DailyWarnings
{
    private const int TTL_SECONDS = 86_400;

    /** @var array<string, true> */
    private array $warned = [];

    /**
     * @param  string  $prefix  keeps the warnings of one feature apart from another's in the cache
     * @param  Closure(): Cache  $cache
     */
    public function __construct(
        private readonly string $prefix,
        private readonly Closure $cache,
    ) {}

    /**
     * Context must never hold settings, props or exception messages: they may carry what a
     * person can see (an email in a provider's error, for instance).
     *
     * @param  string  $id  what makes the warning the same one (owner, reason)
     * @param  array<string, mixed>  $context
     */
    public function warn(string $id, string $message, array $context = []): void
    {
        if (isset($this->warned[$id])) {
            return;
        }

        $this->warned[$id] = true;

        try {
            if (! ($this->cache)()->add($this->prefix.sha1($id), true, self::TTL_SECONDS)) {
                return;
            }
        } catch (Throwable) {
            // Without a cache it still warns, once per process
        }

        Log::warning($message, $context);
    }
}
