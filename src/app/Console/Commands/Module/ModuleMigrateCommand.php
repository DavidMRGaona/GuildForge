<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Console\Command;

final class ModuleMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:migrate
        {module : The name of the module to run migrations for}
        {--rollback : Rollback the last migration batch}
        {--step=1 : Number of migrations to rollback}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run or rollback migrations for a specific module';

    public function __construct(
        private readonly ModuleManagerServiceInterface $moduleManager,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string $moduleName */
        $moduleName = $this->argument('module');

        try {
            $name = new ModuleName($moduleName);

            if ($this->option('rollback')) {
                return $this->handleRollback($name, $moduleName);
            }

            return $this->handleMigrate($name, $moduleName);
        } catch (ModuleNotFoundException) {
            $this->error("Module \"{$moduleName}\" not found.");

            return self::FAILURE;
        }
    }

    private function handleMigrate(ModuleName $name, string $moduleName): int
    {
        $this->info("Running migrations for module: {$moduleName}");

        $count = $this->moduleManager->migrate($name);

        if ($count === 0) {
            $this->info('No migrations to run.');
        } else {
            $this->info("Ran {$count} migration(s).");
        }

        return self::SUCCESS;
    }

    private function handleRollback(ModuleName $name, string $moduleName): int
    {
        /** @var string $stepOption */
        $stepOption = $this->option('step');
        $steps = (int) $stepOption;

        $this->info("Rolling back {$steps} migration(s) for module: {$moduleName}");

        $count = $this->moduleManager->rollback($name, $steps);

        if ($count === 0) {
            $this->info('No migrations to rollback.');
        } else {
            $this->info("Rolled back {$count} migration(s).");
        }

        return self::SUCCESS;
    }
}
