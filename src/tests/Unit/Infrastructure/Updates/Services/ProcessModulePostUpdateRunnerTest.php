<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Updates\Services;

use App\Application\Updates\DTOs\PostUpdateReportDTO;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Infrastructure\Updates\Services\ProcessModulePostUpdateRunner;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class ProcessModulePostUpdateRunnerTest extends TestCase
{
    public function test_it_runs_finish_update_in_a_fresh_php_process(): void
    {
        Process::fake(['*' => Process::result($this->reportLine())]);

        $this->runner()->run(new ModuleName('game-tables'), ModuleVersion::fromString('1.0.19-beta'));

        Process::assertRan(function (PendingProcess $process): bool {
            return $process->command === [ProcessModulePostUpdateRunner::phpBinary(), base_path('artisan'), 'module:finish-update', 'game-tables', '1.0.19-beta', '--no-interaction']
                && $process->path === base_path();
        });
    }

    public function test_it_returns_the_report_printed_by_the_command(): void
    {
        Process::fake(['*' => Process::result("Seeding...\n".$this->reportLine())]);

        $report = $this->runner()->run(new ModuleName('game-tables'), ModuleVersion::fromString('1.0.19-beta'));

        $this->assertSame(['2026_10_04_000000_create_game_tables_publishers_table'], $report->migrations);
        $this->assertSame(['PublishersSeeder'], $report->seeders);
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

        $this->runner()->run(new ModuleName('game-tables'), ModuleVersion::fromString('1.0.19-beta'));
    }

    public function test_it_uses_standard_output_when_the_error_output_is_empty(): void
    {
        // Artisan's $this->error() writes to stdout unless the output is a ConsoleOutput with a separate stderr
        Process::fake(['*' => Process::result(output: 'Health check failed: provider failed', exitCode: 1)]);

        $this->expectException(UpdateException::class);
        $this->expectExceptionMessage('Health check failed: provider failed');

        $this->runner()->run(new ModuleName('game-tables'), ModuleVersion::fromString('1.0.19-beta'));
    }

    public function test_it_throws_when_a_successful_run_prints_no_report(): void
    {
        Process::fake(['*' => Process::result('')]);

        $this->expectException(UpdateException::class);

        $this->runner()->run(new ModuleName('game-tables'), ModuleVersion::fromString('1.0.19-beta'));
    }

    public function test_it_refreshes_caches_in_a_fresh_php_process(): void
    {
        Process::fake();

        $this->runner()->refreshCaches();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [ProcessModulePostUpdateRunner::phpBinary(), base_path('artisan'), 'module:refresh-caches', '--no-interaction']);
    }

    public function test_a_failed_cache_refresh_is_logged_and_does_not_throw(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'view:cache failed', exitCode: 1)]);
        Log::spy();

        $this->runner()->refreshCaches();

        Log::shouldHaveReceived('warning')->once();
    }

    private function runner(): ProcessModulePostUpdateRunner
    {
        return new ProcessModulePostUpdateRunner;
    }

    private function reportLine(): string
    {
        return (new PostUpdateReportDTO(['2026_10_04_000000_create_game_tables_publishers_table'], ['PublishersSeeder']))->toOutputLine();
    }
}
