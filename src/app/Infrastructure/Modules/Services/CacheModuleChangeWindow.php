<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Modules\Services\ModuleChangeWindowInterface;
use App\Domain\Modules\ValueObjects\ModuleSetFingerprint;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The module change window kept in the cache every process of the site shares (the queue
 * restart signal lives there too). Bound once per process: the nesting depth is per process.
 */
final class CacheModuleChangeWindow implements ModuleChangeWindowInterface
{
    public const string IN_PROGRESS_KEY = 'modules:change-in-progress';

    public const string FINGERPRINT_KEY = 'modules:fingerprint';

    /**
     * Seconds a window lasts if the process that opened it dies: above the longest change
     * (a module update job may run for 600 s), so workers never resume halfway through one.
     */
    public const int MAX_SECONDS = 900;

    private int $depth = 0;

    public function __construct(
        private readonly Repository $cache,
        private readonly EnabledModulesResolverInterface $resolver,
    ) {}

    public function run(Closure $change): mixed
    {
        $this->open();

        try {
            return $change();
        } finally {
            $this->close();
        }
    }

    public function open(): void
    {
        if ($this->depth++ > 0) {
            return;
        }

        $this->attempt('open', fn (): bool => $this->cache->put(self::IN_PROGRESS_KEY, true, self::MAX_SECONDS));
    }

    public function close(): void
    {
        if ($this->depth === 0 || --$this->depth > 0) {
            return;
        }

        // The fingerprint goes first: a worker that sees the window closed must already read the new modules
        $this->recordFingerprint();
        $this->attempt('close', fn (): bool => $this->cache->forget(self::IN_PROGRESS_KEY));
    }

    public function inProgress(): bool
    {
        return $this->attempt('read', fn (): bool => $this->cache->has(self::IN_PROGRESS_KEY)) === true;
    }

    public function currentFingerprint(): ?ModuleSetFingerprint
    {
        $value = $this->attempt('read', fn (): mixed => $this->cache->get(self::FINGERPRINT_KEY));

        return is_string($value) && $value !== '' ? ModuleSetFingerprint::fromString($value) : null;
    }

    private function recordFingerprint(): void
    {
        try {
            // Read afresh: this process may have booted with, or cached, the modules from before the change
            $this->resolver->reset();
            $fingerprint = ModuleSetFingerprint::of($this->resolver->modules());
        } catch (Throwable $e) {
            // Unknown modules: no worker can tell whether it is current, so all of them start again
            Log::warning('[Modules] Could not read the enabled modules after a change; restarting the queue workers', ['error' => $e->getMessage()]);
            $this->attempt('forget the fingerprint', fn (): bool => $this->cache->forget(self::FINGERPRINT_KEY));
            $this->attempt('restart the queue workers', fn (): int => Artisan::call('queue:restart'));

            return;
        }

        $this->attempt('record the fingerprint', fn (): bool => $this->cache->forever(self::FINGERPRINT_KEY, $fingerprint->value));
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $operation
     * @return TValue|null
     */
    private function attempt(string $what, Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (Throwable $e) {
            Log::warning("[Modules] The module change window could not {$what}", ['error' => $e->getMessage()]);

            return null;
        }
    }
}
