<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Console\Commands\Module\Concerns\OutputsScaffoldResult;
use Illuminate\Console\Command;

final class ModuleMakeMigrationCommand extends Command
{
    use OutputsScaffoldResult;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-migration
        {module : The name of the module}
        {name : The name of the migration}
        {--create= : The table to be created}
        {--table= : The table to modify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new migration for a module';

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
        /** @var string|null $create */
        $create = $this->option('create');
        /** @var string|null $table */
        $table = $this->option('table');

        $tableName = $create ?? $table;
        // --table alters an existing table; --create (or no option) creates one
        $modify = $create === null && $table !== null;

        $this->info("Creating migration '{$name}' for module '{$module}'...");

        $result = $this->scaffoldingService->createMigration($module, $name, $tableName, $modify);

        return $this->outputResult($result);
    }
}
