<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\DTOs\ScaffoldResultDTO;
use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use Illuminate\Console\Command;

final class ModuleMakeRelationManagerCommand extends Command
{
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

        if ($result->success) {
            $this->newLine();
            $this->warn('Remember to register the Livewire component in your ServiceProvider:');
            $this->line('  Livewire::component(');
            $this->line("      'modules.{$module}.filament.relation-managers.".strtolower($name)."-relation-manager',");
            $this->line("      {$name}RelationManager::class");
            $this->line('  );');
            $this->newLine();
            if ($resource !== null) {
                $this->warn('And extend the core resource:');
                $this->line("  {$resource}Resource::extendRelations([");
                $this->line("      {$name}RelationManager::class,");
                $this->line('  ]);');
            }
        }

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
