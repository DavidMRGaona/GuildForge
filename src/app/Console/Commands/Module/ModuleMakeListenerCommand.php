<?php

declare(strict_types=1);

namespace App\Console\Commands\Module;

use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Console\Commands\Module\Concerns\OutputsScaffoldResult;
use Illuminate\Console\Command;

final class ModuleMakeListenerCommand extends Command
{
    use OutputsScaffoldResult;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-listener
        {module : The name of the module}
        {name : The name of the listener}
        {--event= : The domain event to listen for}
        {--queued : Create a queued (async) listener}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new event listener for a module';

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
        /** @var string|null $event */
        $event = $this->option('event');

        if ($event === null) {
            $event = $this->ask('What domain event should this listener handle?', 'SomeEvent');
        }

        /** @var bool $queued */
        $queued = $this->option('queued');

        $this->info("Creating listener '{$name}' for event '{$event}' in module '{$module}'...");

        $result = $this->scaffoldingService->createListener($module, $name, $event, $queued);

        return $this->outputResult($result);
    }
}
