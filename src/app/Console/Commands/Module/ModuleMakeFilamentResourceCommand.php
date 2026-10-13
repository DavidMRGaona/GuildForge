<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Console\Commands\Module\Concerns\OutputsScaffoldResult;
use Illuminate\Console\Command;

final class ModuleMakeFilamentResourceCommand extends Command
{
    use OutputsScaffoldResult;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-filament-resource
        {module : The name of the module}
        {name : The name of the resource}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Filament resource for a module';

    public function __construct(
        private readonly ModuleScaffoldingServiceInterface $scaffoldingService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string $module */
        $module = $this->argument('module');
        /** @var string $name */
        $name = $this->argument('name');

        $this->info("Creating Filament resource '{$name}' for module '{$module}'...");

        $result = $this->scaffoldingService->createFilamentResource($module, $name);

        return $this->outputResult($result);
    }
}
