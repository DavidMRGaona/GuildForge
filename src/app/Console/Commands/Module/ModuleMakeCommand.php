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
        {--display-name= : The name shown in the admin panel, in Spanish (e.g. "Mesas de rol")}
        {--repository= : The GitHub repository (owner/repo) the updater reads releases from}';

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
        /** @var string|null $displayName */
        $displayName = $this->option('display-name');
        /** @var string|null $repository */
        $repository = $this->option('repository');

        $this->info("Creating module '{$name}'...");

        $result = $this->scaffoldingService->createModule($name, $description, $author, $displayName, $repository);

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
        /** @var string $name */
        $name = $this->argument('name');

        $this->line('Next steps:');
        $this->line('  1. Check "displayName" in module.json and "title" in lang/es/messages.php (shown in the admin panel)');
        $this->line('  2. Run <fg=cyan>php artisan module:discover</> to register the module');
        $this->line("  3. Run <fg=cyan>php artisan module:enable {$name}</> to enable it");
        if ($this->option('repository') === null) {
            $this->line("  4. Set \"repository\" (owner/repo) in module.json, or run <fg=cyan>php artisan module:set-source {$name} owner/repo</> once installed, so updates are found");
        }
        $this->line("  Once it has Vue pages or components: <fg=cyan>php artisan module:build {$name}</> (or npm install && npm run build in the module)");
        $this->line('  Tests: <fg=cyan>vendor/bin/phpunit -c modules/'.$name.'/phpunit.xml</>');

        return self::SUCCESS;
    }
}
