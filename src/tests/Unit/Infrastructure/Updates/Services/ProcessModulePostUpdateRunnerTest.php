<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Updates\Services;

use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Infrastructure\Updates\Services\ProcessModulePostUpdateRunner;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class ProcessModulePostUpdateRunnerTest extends TestCase
{
    public function test_it_runs_finish_update_in_a_fresh_php_process(): void
    {
        Process::fake(['*' => Process::result('game-tables is ready.')]);

        (new ProcessModulePostUpdateRunner)->run(new ModuleName('game-tables'));

        Process::assertRan(function (PendingProcess $process): bool {
            return $process->command === [ProcessModulePostUpdateRunner::phpBinary(), base_path('artisan'), 'module:finish-update', 'game-tables', '--no-interaction']
                && $process->path === base_path();
        });
    }

    public function test_it_throws_with_the_process_output_when_it_fails(): void
    {
        Process::fake(['*' => Process::result(
            output: '',
            errorOutput: 'Target class [PublishersSeeder] does not exist.',
            exitCode: 1,
        )]);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Target class [PublishersSeeder] does not exist.');

        (new ProcessModulePostUpdateRunner)->run(new ModuleName('game-tables'));
    }

    public function test_it_uses_standard_output_when_the_error_output_is_empty(): void
    {
        // Artisan's $this->error() writes to stdout unless the output is a ConsoleOutput with a separate stderr
        Process::fake(['*' => Process::result(output: 'Health check failed: provider failed', exitCode: 1)]);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Health check failed: provider failed');

        (new ProcessModulePostUpdateRunner)->run(new ModuleName('game-tables'));
    }
}
