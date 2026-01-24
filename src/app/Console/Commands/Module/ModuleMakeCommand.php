<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\DTOs\ScaffoldResultDTO;
use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use Illuminate\Console\Command;

final class ModuleMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make
        {name : The name of the module (kebab-case)}
        {--description= : A description for the module}
        {--author= : The author of the module}
        {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new module with the standard directory structure';

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
        /** @var string $name */
        $name = $this->argument('name');
        /** @var string|null $description */
        $description = $this->option('description');
        /** @var string|null $author */
        $author = $this->option('author');

        $this->info("Creating module '{$name}'...");

        $result = $this->scaffoldingService->createModule($name, $description, $author);

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

        foreach ($result->warnings as $warning) {
            $this->warn("  Warning: {$warning}");
        }

        $this->newLine();
        $this->info($result->message);
        $this->newLine();
        $this->line('Next steps:');
        $this->line('  1. Run <fg=cyan>php artisan module:discover</> to register the module');
        $this->line('  2. Run <fg=cyan>php artisan module:enable '.$this->argument('name').'</> to enable it');

        return self::SUCCESS;
    }
}
