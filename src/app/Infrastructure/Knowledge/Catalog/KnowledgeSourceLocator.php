<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Catalog;

use App\Application\Modules\Services\EnabledModulesResolverInterface;

/**
 * Where guide files live: the core's knowledge folder first, then each enabled module's,
 * in alphabetical order of module name.
 */
final readonly class KnowledgeSourceLocator
{
    public function __construct(
        private EnabledModulesResolverInterface $modules,
        private string $corePath,
    ) {}

    /**
     * @return list<array{path: string, module: ?string}>
     */
    public function roots(): array
    {
        $roots = [];
        if (is_dir($this->corePath)) {
            $roots[] = ['path' => $this->corePath, 'module' => null];
        }

        $modules = [];
        foreach ($this->modules->modules() as $module) {
            $modules[$module->name()->value] = $module->path().'/resources/knowledge';
        }
        ksort($modules);

        foreach ($modules as $name => $path) {
            if (is_dir($path)) {
                $roots[] = ['path' => $path, 'module' => $name];
            }
        }

        return $roots;
    }
}
