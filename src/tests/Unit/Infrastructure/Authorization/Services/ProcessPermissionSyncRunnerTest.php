<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Authorization\Services;

use App\Infrastructure\Authorization\Services\ProcessPermissionSyncRunner;
use App\Infrastructure\Updates\Services\ProcessModulePostUpdateRunner;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

final class ProcessPermissionSyncRunnerTest extends TestCase
{
    public function test_it_runs_permissions_sync_in_a_fresh_php_process(): void
    {
        Process::fake();

        (new ProcessPermissionSyncRunner)->sync();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [ProcessModulePostUpdateRunner::phpBinary(), base_path('artisan'), 'permissions:sync', '--no-interaction']
            && $process->path === base_path());
    }

    public function test_a_failed_sync_is_logged_without_throwing(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'SQLSTATE[08006] connection refused', exitCode: 1)]);
        $log = Log::spy();

        (new ProcessPermissionSyncRunner)->sync();

        $log->shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'Could not sync permissions after a module change'
                && $context['error'] === 'SQLSTATE[08006] connection refused'
        );
    }

    public function test_a_process_that_cannot_start_is_logged_without_throwing(): void
    {
        Process::fake(fn (): never => throw new RuntimeException('proc_open is disabled'));
        $log = Log::spy();

        (new ProcessPermissionSyncRunner)->sync();

        $log->shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $context['error'] === 'proc_open is disabled'
        );
    }
}
