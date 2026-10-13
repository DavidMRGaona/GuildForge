<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Console\Commands\Module\Concerns\OutputsScaffoldResult;
use Illuminate\Console\Command;

final class ModuleMakeTestCommand extends Command
{
    use OutputsScaffoldResult;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-test
        {module : The name of the module}
        {name : The name of the test}
        {--unit : Create a unit test (default)}
        {--feature : Create a feature test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new test for a module';

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

        $type = $this->option('feature') ? 'feature' : 'unit';

        $this->info("Creating {$type} test '{$name}' for module '{$module}'...");

        $result = $this->scaffoldingService->createTest($module, $name, $type);

        return $this->outputResult($result);
    }
}
