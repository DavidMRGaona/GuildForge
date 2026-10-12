<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Modules are optional and live in their own repositories: core code that imports
 * one of their classes breaks wherever that module is not installed. Modules
 * extend core models themselves (Model::resolveRelationUsing()).
 */
final class CoreDoesNotDependOnModulesTest extends TestCase
{
    private const string PATTERN = '/^\s*use\s+\\\\?Modules\\\\|(?<![\w\\\\])\\\\?Modules\\\\[\w\\\\]+::class/m';

    public function test_application_code_never_references_module_classes(): void
    {
        $offenders = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app', FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match(self::PATTERN, (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders);
    }
}
