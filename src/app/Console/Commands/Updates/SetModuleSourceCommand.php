<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Console\Command;

/**
 * Sets the GitHub repository of an installed module whose module.json predates
 * the "repository" field, so the updater can find its releases.
 */
final class SetModuleSourceCommand extends Command
{
    protected $signature = 'module:set-source
                            {name : Module name, e.g. game-tables}
                            {repository : GitHub repository as owner/repo}';

    protected $description = 'Set the GitHub repository a module is updated from';

    public function handle(ModuleRepositoryInterface $modules): int
    {
        $repository = (string) $this->argument('repository');

        if (preg_match('#^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)$#', $repository, $matches) !== 1) {
            $this->error("Invalid repository '{$repository}', expected owner/repo.");

            return self::FAILURE;
        }

        $module = $modules->findByName(new ModuleName((string) $this->argument('name')));

        if ($module === null) {
            $this->error("Module '{$this->argument('name')}' is not installed.");

            return self::FAILURE;
        }

        $module->updateSourceInfo($matches[1], $matches[2]);
        $modules->save($module);

        $this->info("{$module->name()->value} will be updated from {$repository}.");

        return self::SUCCESS;
    }
}
