<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates\Services;

use App\Application\Updates\Services\ModulePostUpdateRunnerInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Updates\Exceptions\UpdateException;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Runs `module:finish-update` in a new PHP process.
 *
 * The queue worker performing the update has the previous version's classes and
 * service providers loaded; a fresh process boots the new version only.
 */
final class ProcessModulePostUpdateRunner implements ModulePostUpdateRunnerInterface
{
    /** Below the queue's retry_after (660s), so the job is never redelivered mid-update */
    private const int TIMEOUT_SECONDS = 480;

    public function run(ModuleName $moduleName): void
    {
        $result = Process::path(base_path())
            ->timeout(self::TIMEOUT_SECONDS)
            ->run([self::phpBinary(), base_path('artisan'), 'module:finish-update', $moduleName->value, '--no-interaction']);

        if ($result->successful()) {
            return;
        }

        $output = trim($result->errorOutput()) !== '' ? $result->errorOutput() : $result->output();
        $reason = trim($output) !== '' ? trim($output) : "exit code {$result->exitCode()}";

        throw UpdateException::postUpdateFailed($moduleName->value, $reason);
    }

    /**
     * The CLI binary, also when the update runs inside PHP-FPM (sync queue),
     * where PHP_BINARY points to php-fpm.
     */
    public static function phpBinary(): string
    {
        return (new PhpExecutableFinder)->find(false) ?: 'php';
    }
}
