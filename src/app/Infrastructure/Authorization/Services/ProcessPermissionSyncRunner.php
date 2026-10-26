<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\PermissionSyncRunnerInterface;
use App\Infrastructure\Updates\Services\ProcessModulePostUpdateRunner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

final class ProcessPermissionSyncRunner implements PermissionSyncRunnerInterface
{
    private const int TIMEOUT_SECONDS = 120;

    public function sync(): void
    {
        try {
            $result = Process::path(base_path())
                ->timeout(self::TIMEOUT_SECONDS)
                ->run([ProcessModulePostUpdateRunner::phpBinary(), base_path('artisan'), 'permissions:sync', '--no-interaction']);

            if (! $result->successful()) {
                $output = trim($result->errorOutput()) !== '' ? $result->errorOutput() : $result->output();

                Log::warning('Could not sync permissions after a module change', [
                    'error' => trim($output) !== '' ? trim($output) : "exit code {$result->exitCode()}",
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not sync permissions after a module change', ['error' => $e->getMessage()]);
        }
    }
}
