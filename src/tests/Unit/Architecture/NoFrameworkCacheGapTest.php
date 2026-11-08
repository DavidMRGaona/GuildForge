<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\Files\ReadsFiles;

/**
 * Clearing the config, route or event cache while the site serves requests leaves a gap
 * in which a request sees the cache, then fails to load it (core#408). Application code
 * rebuilds them in place with App\Infrastructure\Support\AtomicFrameworkCacheRebuilder
 * (php artisan module:refresh-caches) instead.
 */
final class NoFrameworkCacheGapTest extends TestCase
{
    use ReadsFiles;

    private const string PATTERN = '/\b(?:call|callSilent|callSilently|run)\s*\(\s*[\'"](?:config|route|event):clear[\'"]/i';

    public function test_application_code_never_clears_a_cache_it_should_rebuild_in_place(): void
    {
        $offenders = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app', FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match(self::PATTERN, self::readFile($file->getPathname())) === 1) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders, 'These files clear a framework cache instead of rebuilding it in place: '.implode(', ', $offenders));
    }

    #[DataProvider('gapCalls')]
    public function test_pattern_catches_cache_clears(string $code): void
    {
        $this->assertSame(1, preg_match(self::PATTERN, $code), "Pattern misses: {$code}");
    }

    #[DataProvider('harmlessCalls')]
    public function test_pattern_ignores_harmless_calls(string $code): void
    {
        $this->assertSame(0, preg_match(self::PATTERN, $code), "Pattern wrongly matches: {$code}");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function gapCalls(): array
    {
        return [
            'artisan route:clear' => ["Artisan::call('route:clear');"],
            'artisan config:clear' => ['Artisan::call("config:clear");'],
            'artisan event:clear' => ["Artisan::call( 'event:clear' );"],
            'command callSilent' => ["\$this->callSilent('route:clear');"],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function harmlessCalls(): array
    {
        return [
            'view clear' => ["Artisan::call('view:clear');"],
            'command name in a list' => ["'route:cache', 'route:clear', 'clear-compiled',"],
            'cache rebuild' => ["Artisan::call('route:cache');"],
        ];
    }
}
