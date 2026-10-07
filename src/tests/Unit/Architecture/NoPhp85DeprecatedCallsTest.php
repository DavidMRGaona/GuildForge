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
 * Calls deprecated in PHP 8.5 that the application and its tests do not need:
 * ReflectionProperty/ReflectionMethod::setAccessible() (no effect since PHP 8.1),
 * imagedestroy() (no effect since PHP 8.0) and the driver-specific PDO::MYSQL_*,
 * PDO::PGSQL_* and PDO::SQLITE_* constants (replaced by Pdo\Mysql, Pdo\Pgsql and
 * Pdo\Sqlite).
 */
final class NoPhp85DeprecatedCallsTest extends TestCase
{
    private const string PATTERN = '/->setAccessible\s*\(|\bimagedestroy\s*\(|\bPDO::(MYSQL|PGSQL|SQLITE)_/';

    private const array DIRECTORIES = ['app', 'config', 'database', 'routes', 'tests'];

    public function test_own_code_has_no_calls_deprecated_in_php_85(): void
    {
        $root = dirname(__DIR__, 3);
        $offenders = [];

        foreach (self::DIRECTORIES as $directory) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS),
            );

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php' || $file->getRealPath() === __FILE__) {
                    continue;
                }

                if (preg_match(self::PATTERN, (string) file_get_contents($file->getPathname())) === 1) {
                    $offenders[] = substr($file->getPathname(), strlen($root) + 1);
                }
            }
        }

        sort($offenders);

        $this->assertSame([], $offenders, 'These files use calls deprecated in PHP 8.5: '.implode(', ', $offenders));
    }

    #[DataProvider('deprecatedCalls')]
    public function test_pattern_catches_deprecated_calls(string $code): void
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
    public static function deprecatedCalls(): array
    {
        return [
            'property setAccessible' => ['$property->setAccessible(true);'],
            'method setAccessible with a space' => ['$method->setAccessible (true);'],
            'imagedestroy' => ['imagedestroy($image);'],
            'global imagedestroy' => ['\imagedestroy($image);'],
            'PDO MySQL constant' => ["\\PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),"],
            'PDO PostgreSQL constant' => ['\\PDO::PGSQL_ATTR_DISABLE_PREPARES => true,'],
            'PDO SQLite constant' => ['$flags = PDO::SQLITE_DETERMINISTIC;'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function harmlessCalls(): array
    {
        return [
            'Pdo\Mysql constant' => ["\\Pdo\\Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),"],
            'Pdo\Pgsql constant' => ['\\Pdo\\Pgsql::ATTR_DISABLE_PREPARES => true,'],
            'Pdo\Sqlite constant' => ['$flags = \\Pdo\\Sqlite::DETERMINISTIC;'],
            'env key with the old name' => ["env('MYSQL_ATTR_SSL_CA')"],
            'reflection getValue' => ['$property->getValue($instance);'],
            'GD image creation' => ['imagecreatetruecolor(10, 10);'],
        ];
    }
}
