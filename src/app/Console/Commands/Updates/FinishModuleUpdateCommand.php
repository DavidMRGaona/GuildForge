<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Updates\DTOs\PostUpdateReportDTO;
use App\Application\Updates\Services\ModuleHealthCheckerInterface;
use App\Domain\Authorization\Enums\RoleAuditSource;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Authorization\Services\RoleAuditContext;
use App\Infrastructure\Modules\Services\ModulePathResolver;
use App\Infrastructure\Modules\Services\ModuleSchemaGuard;
use App\Infrastructure\Modules\Services\ModuleSeederRunner;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
 * the database (permissions, migrations, seeders and the recorded version) then runs
 * in one transaction: PostgreSQL rolls DDL back too, so a failure at any point leaves the
 * database as it was and the updater can safely restore the previous files. The
 * commit is the point of no return.
 *
 * The updater passes what the role history must record (--audit-source, --audit-actor):
 * this process alone would see a console command run by nobody. Without them it records
 * just that.
 */
final class FinishModuleUpdateCommand extends Command
{
    protected $signature = 'module:finish-update
        {name : Module name}
        {version : Version whose files are now installed}
        {--audit-source= : Source recorded in the role history, the updater\'s own}
        {--audit-actor= : Id of the user who requested the update, recorded in the role history}';

    protected $description = 'Run the health check, migrations and seeders of a module whose files were just updated (used by the updater)';

    protected $hidden = true;

    public function handle(
        ModuleRepositoryInterface $modules,
        ModuleSchemaGuard $schemaGuard,
        ModuleSeederRunner $seederRunner,
        ModuleHealthCheckerInterface $healthChecker,
        ModulePathResolver $paths,
        PermissionRegistryInterface $permissions,
        RoleAuditContext $auditContext,
    ): int {
        $sourceOption = $this->option('audit-source');
        $source = is_string($sourceOption) ? RoleAuditSource::tryFrom($sourceOption) : null;

        if (is_string($sourceOption) && ! $source instanceof RoleAuditSource) {
            $this->error("Unknown role history source '{$sourceOption}'.");

            return self::FAILURE;
        }

        return $auditContext->actingFor(
            $this->auditActorId(),
            fn (): int => $this->finish($modules, $schemaGuard, $seederRunner, $healthChecker, $paths, $permissions),
            $source,
        );
    }

    private function finish(
        ModuleRepositoryInterface $modules,
        ModuleSchemaGuard $schemaGuard,
        ModuleSeederRunner $seederRunner,
        ModuleHealthCheckerInterface $healthChecker,
        ModulePathResolver $paths,
        PermissionRegistryInterface $permissions,
    ): int {
        try {
            /** @var MigrationRepositoryInterface $migrationRepository */
            $migrationRepository = $this->laravel->make('migration.repository');

            $name = new ModuleName((string) $this->argument('name'));
            $version = ModuleVersion::fromString((string) $this->argument('version'));
            $module = $modules->findByName($name);

            if (! $module instanceof Module) {
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

            $report = DB::transaction(function () use ($module, $modules, $version, $schemaGuard, $seederRunner, $migrationRepository, $paths, $permissions): PostUpdateReportDTO {
                // This process booted the new provider, so the registry holds the new version's
                // permissions; before the migrations, which may grant one to a role
                $permissions->syncToDatabase();

                $migrations = [];
                $migrationsPath = $paths->resolve($module).'/database/migrations';

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

    /**
     * The requester, if their account still exists: the update goes ahead without one.
     */
    private function auditActorId(): ?string
    {
        $actorId = $this->option('audit-actor');

        if (! is_string($actorId) || $actorId === '') {
            return null;
        }

        // Checked first: PostgreSQL rejects a malformed value in a uuid comparison
        if (Str::isUuid($actorId) && UserModel::query()->whereKey($actorId)->exists()) {
            return $actorId;
        }

        Log::warning('The user who requested a module update has no account; the role history records no actor', [
            'module' => $this->argument('name'),
            'actor_id' => $actorId,
        ]);

        return null;
    }
}
