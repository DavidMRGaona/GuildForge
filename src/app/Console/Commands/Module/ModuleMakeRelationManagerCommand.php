<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Console\Commands\Module\Concerns\OutputsScaffoldResult;
use Illuminate\Console\Command;

final class ModuleMakeRelationManagerCommand extends Command
{
    use OutputsScaffoldResult;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-relation-manager
        {module : The name of the module}
        {name : The name of the relation manager (e.g., Registrations)}
        {--resource= : The core resource to extend (e.g., Event)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Filament RelationManager for a module';

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
        /** @var string|null $resource */
        $resource = $this->option('resource');

        $this->info("Creating RelationManager '{$name}' for module '{$module}'...");

        $result = $this->scaffoldingService->createRelationManager($module, $name, $resource);

        return $this->outputResult($result);
    }
}
