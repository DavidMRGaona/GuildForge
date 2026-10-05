<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * cache:clear and optimize:clear run FLUSHDB on the Redis cache database, which
 * production shares between tenants. Application code must use
 * App\Infrastructure\Support\FrameworkCacheCommands::CLEAR instead.
 */
final class NoDataCacheFlushTest extends TestCase
{
    private const string PATTERN = '/[\'"](?:cache:clear|optimize:clear)[\'"]|Cache::flush\(|cache\(\)->flush\(|->flushdb\(/i';

    public function test_application_code_never_flushes_the_data_cache(): void
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

        $this->assertSame([], $offenders, 'These files flush the shared data cache: '.implode(', ', $offenders));
    }
}
