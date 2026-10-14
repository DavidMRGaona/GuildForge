<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Console\Commands\Module\Concerns\OutputsScaffoldResult;
use Illuminate\Console\Command;

final class ModuleMakeDtoCommand extends Command
{
    use OutputsScaffoldResult;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-dto
        {module : The name of the module}
        {name : The name of the DTO}
        {--response : Create a response DTO}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new DTO for a module';

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
        $isResponse = (bool) $this->option('response');

        $type = $isResponse ? 'response DTO' : 'DTO';
        $this->info("Creating {$type} '{$name}' for module '{$module}'...");

        $result = $this->scaffoldingService->createDto($module, $name, $isResponse);

        return $this->outputResult($result);
    }
}
