<?php

declare(strict_types=1);

namespace App\Infrastructure\Support;

use App\Infrastructure\Updates\Services\ProcessModulePostUpdateRunner;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Rebuilds a framework cache without a moment in which the file is missing or half written.
 *
 * config:cache, route:cache and event:cache delete the cache before writing the new one,
 * and write it in place: a request arriving in between sees the cache and then fails to
 * require it (core#408). Here the command runs in a child process whose path variable
 * points to a new file beside the current one, so the child does not see the old cache
 * (it builds from the sources, as after a clear) and the site keeps loading it. The new
 * file then replaces the old one with a single rename, which is atomic on one filesystem.
 */
final readonly class AtomicFrameworkCacheRebuilder
{
    private const int TIMEOUT_SECONDS = 120;

    public function __construct(
        private Application $app,
    ) {}

    /**
     * @return bool True when the new cache is in place; false when the build failed and the
     *              stale cache was removed instead (logged)
     */
    public function rebuild(FrameworkCache $cache): bool
    {
        $target = $cache->path($this->app);
        $next = $this->nextPath($target);

        try {
            $result = Process::path($this->app->basePath())
                ->timeout(self::TIMEOUT_SECONDS)
                ->env([$cache->pathVariable() => $next])
                ->run([ProcessModulePostUpdateRunner::phpBinary(), $this->app->basePath('artisan'), $cache->cacheCommand(), '--no-interaction']);

            if (! $result->successful()) {
                throw new RuntimeException(trim($result->errorOutput()) ?: trim($result->output()) ?: 'exit code '.$result->exitCode());
            }

            clearstatcache(true, $next);

            if (! is_file($next) || filesize($next) === 0) {
                throw new RuntimeException('the command wrote no cache file');
            }

            if (! @rename($next, $target)) {
                throw new RuntimeException('could not move the new cache into place');
            }

            return true;
        } catch (\Throwable $e) {
            File::delete($next);
            // A stale cache hides what the module change added (routes, settings, listeners);
            // without one the site reads the sources, which always works
            File::delete($target);

            Log::warning('Could not rebuild a framework cache; it was cleared instead', [
                'cache' => $cache->value,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** Beside the target, so the rename never crosses filesystems. */
    private function nextPath(string $target): string
    {
        return dirname($target).'/'.basename($target, '.php').'.next-'.bin2hex(random_bytes(6)).'.php';
    }
}
