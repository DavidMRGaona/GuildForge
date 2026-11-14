<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Console\Concerns\ReadsTypedInput;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Console\Command;

/**
 * Sets the GitHub repository of an installed module whose module.json predates
 * the "repository" field, so the updater can find its releases.
 */
final class SetModuleSourceCommand extends Command
{
    use ReadsTypedInput;

    protected $signature = 'module:set-source
                            {name : Module name, e.g. game-tables}
                            {repository : GitHub repository as owner/repo}';

    protected $description = 'Set the GitHub repository a module is updated from';

    public function handle(ModuleRepositoryInterface $modules): int
    {
        $repository = $this->stringArgument('repository');

        if (preg_match('#^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)$#', $repository, $matches) !== 1) {
            $this->error("Invalid repository '{$repository}', expected owner/repo.");

            return self::FAILURE;
        }

        $name = $this->stringArgument('name');
        $module = $modules->findByName(new ModuleName($name));

        if (! $module instanceof Module) {
            $this->error("Module '{$name}' is not installed.");

            return self::FAILURE;
        }

        $module->updateSourceInfo($matches[1], $matches[2]);
        $modules->save($module);

        $this->info("{$module->name()->value} will be updated from {$repository}.");

        return self::SUCCESS;
    }
}
