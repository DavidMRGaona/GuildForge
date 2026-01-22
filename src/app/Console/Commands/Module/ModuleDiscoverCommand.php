<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use Illuminate\Console\Command;

final class ModuleDiscoverCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:discover';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Discover modules from the filesystem';

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
        $this->info('Discovering modules...');

        $discovered = $this->moduleManager->discover();

        $count = $discovered->count();

        if ($count === 0) {
            $this->info('No modules found.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} module(s).");

        return self::SUCCESS;
    }
}
