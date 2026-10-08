<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Content;

use App\Application\Content\DTOs\TrixContentMigrationReportDTO;
use App\Application\Content\Services\TrixContentMigratorInterface;
use Mockery;
use Tests\TestCase;

final class ConvertTrixContentCommandTest extends TestCase
{
    public function test_a_dry_run_lists_what_would_change(): void
    {
        $migrator = Mockery::mock(TrixContentMigratorInterface::class);
        $migrator->shouldReceive('migrate')->once()->with(true)->andReturn(new TrixContentMigrationReportDTO(7, ['articles.content#a1', 'settings#about_history'], null));
        $this->app->instance(TrixContentMigratorInterface::class, $migrator);

        $this->artisan('content:convert-trix', ['--dry-run' => true])
            ->expectsOutput('  articles.content#a1')
            ->expectsOutput('  settings#about_history')
            ->expectsOutput('7 value(s) checked, 2 would change.')
            ->assertExitCode(0);
    }

    public function test_a_run_reports_where_the_originals_were_saved(): void
    {
        $migrator = Mockery::mock(TrixContentMigratorInterface::class);
        $migrator->shouldReceive('migrate')->once()->with(false)->andReturn(new TrixContentMigrationReportDTO(7, ['articles.content#a1'], '/backups/trix.json'));
        $this->app->instance(TrixContentMigratorInterface::class, $migrator);

        $this->artisan('content:convert-trix')
            ->expectsOutput('7 value(s) checked, 1 changed.')
            ->expectsOutput('Original values saved to /backups/trix.json')
            ->assertExitCode(0);
    }

    public function test_restore_puts_a_backup_back(): void
    {
        $migrator = Mockery::mock(TrixContentMigratorInterface::class);
        $migrator->shouldReceive('restore')->once()->with('/backups/trix.json')->andReturn(3);
        $migrator->shouldNotReceive('migrate');
        $this->app->instance(TrixContentMigratorInterface::class, $migrator);

        $this->artisan('content:convert-trix', ['--restore' => '/backups/trix.json'])
            ->expectsOutput('Restored 3 value(s) from /backups/trix.json.')
            ->assertExitCode(0);
    }
}
