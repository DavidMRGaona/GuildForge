<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\Files\ReadsFiles;

/**
 * Modules are optional and live in their own repositories: core code that imports
 * one of their classes breaks wherever that module is not installed. Modules
 * extend core models themselves (Model::resolveRelationUsing()).
 */
final class CoreDoesNotDependOnModulesTest extends TestCase
{
    use ReadsFiles;

    private const string PATTERN = '/^\s*use\s+\\\\?Modules\\\\|(?<![\w\\\\])\\\\?Modules\\\\[\w\\\\]+::class/m';

    private const array DIRECTORIES = ['app', 'bootstrap', 'config', 'database', 'routes'];

    public function test_core_code_never_references_module_classes(): void
    {
        $offenders = [];

        foreach (self::DIRECTORIES as $directory) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/'.$directory, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php' || str_contains($file->getPathname(), '/bootstrap/cache/')) {
                    continue;
                }

                if (preg_match(self::PATTERN, self::readFile($file->getPathname())) === 1) {
                    $offenders[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $offenders);
    }
}
