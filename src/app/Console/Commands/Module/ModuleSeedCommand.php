<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Console\Command;

final class ModuleSeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:seed
        {module : The name of the module to run seeders for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run seeders for a specific module';

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

            $this->info("Running seeders for module: {$moduleName}");

            $count = $this->moduleManager->seed($name);

            if ($count === 0) {
                $this->info('No seeders to run.');
            } else {
                $this->info("Ran {$count} seeder(s).");
            }

            return self::SUCCESS;
        } catch (ModuleNotFoundException) {
            $this->error("Module \"{$moduleName}\" not found.");

            return self::FAILURE;
        }
    }
}
