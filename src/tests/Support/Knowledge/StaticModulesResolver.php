<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Domain\Modules\Entities\Module;

final readonly class StaticModulesResolver implements EnabledModulesResolverInterface
{
    /**
     * @param  list<Module>  $modules
     */
    public function __construct(private array $modules) {}

    public function names(): array
    {
        return array_map(static fn (Module $module): string => $module->name()->value, $this->modules);
    }

    public function modules(): array
    {
        return $this->modules;
    }

    public function rejected(): array
    {
        return [];
    }

    public function migrationPaths(): array
    {
        return [];
    }

    public function reset(): void {}
}
