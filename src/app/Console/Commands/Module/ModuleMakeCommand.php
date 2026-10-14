<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Console\Commands\Module\Concerns\OutputsScaffoldResult;
use Illuminate\Console\Command;

final class ModuleMakeCommand extends Command
{
    use OutputsScaffoldResult;

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
}
