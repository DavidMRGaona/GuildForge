<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Application\Updates\DTOs\PostUpdateReportDTO;
use App\Application\Updates\Services\ModuleHealthCheckerInterface;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\ModuleSchemaGuard;
use App\Infrastructure\Modules\Services\ModuleSeederRunner;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Second half of a module update, run by the updater in a fresh PHP process right
 * after the new files are in place.
 *
 * The queue worker that swapped the files still has the old module classes and
 * providers loaded, so running migrations, seeders or the health check there mixes
 * old and new code. A new process boots with the new version only.
 *
 * The health check only inspects code, so it runs first. Everything that changes
 * the database (migrations, seeders and the recorded version) then runs in one
 * transaction: PostgreSQL rolls DDL back too, so a failure at any point leaves the
 * database as it was and the updater can safely restore the previous files. The
 * commit is the point of no return.
 */
final class FinishModuleUpdateCommand extends Command
{
    protected $signature = 'module:finish-update
        {name : Module name}
        {version : Version whose files are now installed}';

    protected $description = 'Run the health check, migrations and seeders of a module whose files were just updated (used by the updater)';

    protected $hidden = true;

    public function handle(
        ModuleRepositoryInterface $modules,
        ModuleSchemaGuard $schemaGuard,
        ModuleSeederRunner $seederRunner,
        ModuleHealthCheckerInterface $healthChecker,
    ): int {
        try {
            /** @var MigrationRepositoryInterface $migrationRepository */
            $migrationRepository = $this->laravel->make('migration.repository');

            $name = new ModuleName((string) $this->argument('name'));
            $version = ModuleVersion::fromString((string) $this->argument('version'));
            $module = $modules->findByName($name);

            if ($module === null) {
                $this->error("Module '{$name->value}' is not installed.");

                return self::FAILURE;
            }

            if ((bool) config('updates.behavior.health_check', true)) {
                $health = $healthChecker->check($name);

                if (! $health->passes()) {
                    $this->error('Health check failed: '.implode(', ', $health->errors));

                    return self::FAILURE;
                }
            }

            $report = DB::transaction(function () use ($module, $modules, $version, $schemaGuard, $seederRunner, $migrationRepository): PostUpdateReportDTO {
                $migrations = [];
                $migrationsPath = $module->path().'/database/migrations';

                if (is_dir($migrationsPath)) {
                    $before = $migrationRepository->getRan();
                    $exitCode = $schemaGuard->protect($module->name()->value, fn (): int => $this->callSilently('migrate', [
                        '--path' => $migrationsPath,
                        '--realpath' => true,
                        '--force' => true,
                    ]));

                    if ($exitCode !== self::SUCCESS) {
                        throw new RuntimeException("Migrations of {$module->name()->value} failed (exit code {$exitCode}).");
                    }

                    $migrations = array_values(array_diff($migrationRepository->getRan(), $before));
                }

                $seeders = array_map(class_basename(...), $seederRunner->seed($module));

                $module->updateVersion($version);
                $module->clearLatestAvailableVersion();
                $modules->save($module);

                return new PostUpdateReportDTO($migrations, $seeders);
            });
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($report->toOutputLine());

        return self::SUCCESS;
    }
}
