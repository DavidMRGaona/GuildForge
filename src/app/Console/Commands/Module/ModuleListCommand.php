<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use Illuminate\Console\Command;

final class ModuleListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all registered modules';

    public function __construct(
        private readonly ModuleManagerServiceInterface $moduleManager,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modules = $this->moduleManager->all();

        if ($modules->isEmpty()) {
            $this->info('No modules found.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($modules->all() as $module) {
            $rows[] = [
                $module->name()->value,
                $module->version()->value(),
                $module->status()->value,
                $module->description(),
            ];
        }

        $this->table(
            ['Name', 'Version', 'Status', 'Description'],
            $rows
        );

        return self::SUCCESS;
    }
}
