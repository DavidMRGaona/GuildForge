<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteBlocks;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Warnings about site blocks, once a day each. The footer is on every page and every request
 * builds the registry and the renderer again, so a broken block would otherwise log on every
 * request: each warning is remembered in the cache for a day (and in memory, for the process).
 * Without a cache it still warns, once per process. The cache is asked for only when warning,
 * so building the registry at boot touches nothing.
 */
final class SiteBlockWarnings
{
    private const string CACHE_PREFIX = 'site_blocks:warned:';

    private const int TTL_SECONDS = 86_400;

    /** @var array<string, true> */
    private array $warned = [];

    /**
     * @param  Closure(): Cache  $cache
     */
    public function __construct(
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
            if (! ($this->cache)()->add(self::CACHE_PREFIX.sha1($id), true, self::TTL_SECONDS)) {
                return;
            }
        } catch (Throwable) {
            // Without a cache it still warns, once per process
        }

        Log::warning($message, $context);
    }
}
