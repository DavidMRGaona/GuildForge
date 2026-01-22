<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Exceptions\ModuleAlreadyDisabledException;
use App\Domain\Modules\Exceptions\ModuleDependencyException;
use App\Domain\Modules\Exceptions\ModuleNotFoundException;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Console\Command;

final class ModuleDisableCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:disable {module : The name of the module to disable}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable a module';

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
            $this->moduleManager->disable($name);
            $this->info("Module \"{$moduleName}\" has been disabled.");

            return self::SUCCESS;
        } catch (ModuleNotFoundException) {
            $this->error("Module \"{$moduleName}\" not found.");

            return self::FAILURE;
        } catch (ModuleAlreadyDisabledException) {
            $this->error("Module \"{$moduleName}\" is already disabled.");

            return self::FAILURE;
        } catch (ModuleDependencyException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
