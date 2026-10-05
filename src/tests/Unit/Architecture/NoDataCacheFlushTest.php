<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * Artisan clear commands, any static or instance flush()/flushdb()/flushall()
     * (Cache facade, cache stores, Redis), and PSR-16 clear() on a cache store.
     */
    private const string PATTERN = '/[\'"](?:cache:clear|optimize:clear)[\'"]'
        .'|(?:::|->)flush(?:db|all)?\s*\('
        .'|Cache::(?:store|driver)\([^;]*?\)->clear\s*\('
        .'|cache\(\)->clear\s*\(/i';

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

    #[DataProvider('flushingCalls')]
    public function test_pattern_catches_data_cache_flushes(string $code): void
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
    public static function flushingCalls(): array
    {
        return [
            'artisan cache:clear' => ["Artisan::call('cache:clear');"],
            'artisan optimize:clear' => ['Artisan::call("optimize:clear");'],
            'Cache facade flush' => ['Cache::flush();'],
            'cache helper flush' => ['cache()->flush();'],
            'store flush' => ["Cache::store('redis')->flush();"],
            'driver flush' => ['Cache::driver()->flush();'],
            'static Redis flushdb' => ['Redis::flushdb();'],
            'static Redis flushall' => ['Redis::flushall();'],
            'instance flushdb' => ['$redis->flushdb();'],
            'instance flushall' => ['$connection->flushAll ();'],
            'store PSR-16 clear' => ["Cache::store('redis')->clear();"],
            'driver PSR-16 clear' => ['Cache::driver()->clear();'],
            'store with nested call PSR-16 clear' => ["Cache::store(config('cache.default'))->clear();"],
            'cache helper PSR-16 clear' => ['cache()->clear();'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function harmlessCalls(): array
    {
        return [
            'framework cache commands constant' => ['Artisan::call(FrameworkCacheCommands::CLEAR);'],
            'config clear' => ["Artisan::call('config:clear');"],
            'cache forget' => ["Cache::forget('key');"],
            'collection clear unrelated to cache' => ['$this->items->clear();'],
            'flush word in a name' => ['$this->flushMessages = true;'],
        ];
    }
}
