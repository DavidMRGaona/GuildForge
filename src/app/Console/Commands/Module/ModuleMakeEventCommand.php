<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Console\Commands\Module\Concerns\OutputsScaffoldResult;
use Illuminate\Console\Command;

final class ModuleMakeEventCommand extends Command
{
    use OutputsScaffoldResult;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-event
        {module : The name of the module}
        {name : The name of the domain event}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new domain event for a module';

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

        $this->info("Creating domain event '{$name}' for module '{$module}'...");

        $result = $this->scaffoldingService->createDomainEvent($module, $name);

        return $this->outputResult($result);
    }
}
