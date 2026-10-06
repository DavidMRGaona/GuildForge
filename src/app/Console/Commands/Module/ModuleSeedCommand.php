<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleCompatibilityServiceInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Exceptions\ModuleIncompatibleException;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\View\Modules\CompatibilityIssueFormatter;
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
        private readonly ModuleCompatibilityServiceInterface $compatibility,
        private readonly CompatibilityIssueFormatter $formatter,
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
            $module = $this->moduleManager->find($name);

            if ($module === null) {
                throw ModuleNotFoundException::withName($moduleName);
            }

            // Running its seeders would compile the module's code
            $compatibility = $this->compatibility->checkInstalled($name);

            if (! $compatibility->isCompatible()) {
                throw ModuleIncompatibleException::forModule($moduleName, $module->version()->value(), $compatibility);
            }

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
        } catch (ModuleIncompatibleException $e) {
            $this->error($this->formatter->cannotEnable($moduleName, $e->issues));

            return self::FAILURE;
        }
    }
}
