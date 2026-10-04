<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Updates\DTOs\PostUpdateReportDTO;
use App\Application\Updates\Services\ModulePostUpdateRunnerInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Exceptions\UpdateException;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Runs the post-update Artisan commands in new PHP processes.
 *
 * The queue worker performing the update has the previous version's classes and
 * service providers loaded; a fresh process boots the new version only.
 */
final class ProcessModulePostUpdateRunner implements ModulePostUpdateRunnerInterface
{
    /** Below the queue's retry_after (660s), so the job is never redelivered mid-update */
    private const int TIMEOUT_SECONDS = 480;

    private const int CACHE_TIMEOUT_SECONDS = 120;

    public function run(ModuleName $moduleName, ModuleVersion $version): PostUpdateReportDTO
    {
        // A timeout kills the child before it commits, and PostgreSQL rolls its transaction back
        $result = Process::path(base_path())
            ->timeout(self::TIMEOUT_SECONDS)
            ->run([self::phpBinary(), base_path('artisan'), 'module:finish-update', $moduleName->value, $version->value(), '--no-interaction']);

        if (! $result->successful()) {
            throw UpdateException::postUpdateFailed($moduleName->value, self::reason($result));
        }

        return PostUpdateReportDTO::fromOutput($result->output())
            ?? throw UpdateException::postUpdateFailed($moduleName->value, 'the command did not report what it applied');
    }

    public function refreshCaches(): void
    {
        try {
            $result = Process::path(base_path())
                ->timeout(self::CACHE_TIMEOUT_SECONDS)
                ->run([self::phpBinary(), base_path('artisan'), 'module:refresh-caches', '--no-interaction']);

            if (! $result->successful()) {
                Log::warning('Could not refresh caches after a module update', ['error' => self::reason($result)]);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not refresh caches after a module update', ['error' => $e->getMessage()]);
        }
    }

    /**
     * The CLI binary, also when the update runs inside PHP-FPM (sync queue),
     * where PHP_BINARY points to php-fpm.
     */
    public static function phpBinary(): string
    {
        return (new PhpExecutableFinder)->find(false) ?: 'php';
    }

    private static function reason(ProcessResult $result): string
    {
        $output = trim($result->errorOutput()) !== '' ? $result->errorOutput() : $result->output();

        return trim($output) !== '' ? trim($output) : "exit code {$result->exitCode()}";
    }
}
