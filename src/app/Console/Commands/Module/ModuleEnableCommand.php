<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Exceptions\ModuleAlreadyEnabledException;
use App\Domain\Modules\Exceptions\ModuleDependencyException;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Console\Command;

final class ModuleEnableCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:enable {module : The name of the module to enable}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable a module';

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

            // Check dependencies first
            $dependencyCheck = $this->moduleManager->checkDependencies($name);
            if ($dependencyCheck->hasErrors()) {
                $missing = implode(', ', $dependencyCheck->missing);
                $this->error("Cannot enable module \"{$moduleName}\". Missing dependencies: {$missing}");

                return self::FAILURE;
            }

            $this->moduleManager->enable($name);
            $this->info("Module \"{$moduleName}\" has been enabled.");

            return self::SUCCESS;
        } catch (ModuleNotFoundException) {
            $this->error("Module \"{$moduleName}\" not found.");

            return self::FAILURE;
        } catch (ModuleAlreadyEnabledException) {
            $this->error("Module \"{$moduleName}\" is already enabled.");

            return self::FAILURE;
        } catch (ModuleDependencyException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
