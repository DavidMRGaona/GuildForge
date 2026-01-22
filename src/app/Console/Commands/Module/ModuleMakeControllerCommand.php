<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\DTOs\ScaffoldResultDTO;
use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use Illuminate\Console\Command;

final class ModuleMakeControllerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-controller
        {module : The name of the module}
        {name : The name of the controller}
        {--resource : Create a resource controller}
        {--api : Create an API controller}
        {--invokable : Create an invokable controller}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new controller for a module';

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

        $type = 'default';
        if ($this->option('resource')) {
            $type = 'resource';
        } elseif ($this->option('api')) {
            $type = 'api';
        } elseif ($this->option('invokable')) {
            $type = 'invokable';
        }

        $this->info("Creating {$type} controller '{$name}' for module '{$module}'...");

        $result = $this->scaffoldingService->createController($module, $name, $type);

        return $this->outputResult($result);
    }

    private function outputResult(ScaffoldResultDTO $result): int
    {
        if ($result->isFailure()) {
            $this->error($result->message);
            foreach ($result->errors as $error) {
                $this->line("  - {$error}");
            }

            return self::FAILURE;
        }

        foreach ($result->files as $file => $status) {
            $statusLabel = match ($status) {
                ScaffoldResultDTO::STATUS_CREATED => '<fg=green>[CREATED]</>',
                ScaffoldResultDTO::STATUS_SKIPPED => '<fg=yellow>[SKIPPED]</>',
                ScaffoldResultDTO::STATUS_OVERWRITTEN => '<fg=blue>[OVERWRITTEN]</>',
                ScaffoldResultDTO::STATUS_FAILED => '<fg=red>[FAILED]</>',
                default => "<fg=gray>[{$status}]</>",
            };

            $this->line("  {$statusLabel} {$file}");
        }

        $this->newLine();
        $this->info($result->message);

        return self::SUCCESS;
    }
}
