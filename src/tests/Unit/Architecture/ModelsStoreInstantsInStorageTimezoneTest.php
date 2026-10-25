<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use FilesystemIterator;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Tests\Support\Files\ReadsFiles;

/**
 * Eloquent formats a DateTimeInterface in its own zone without converting, so every
 * model must convert instants to the storage zone before they are written.
 */
final class ModelsStoreInstantsInStorageTimezoneTest extends TestCase
{
    use ReadsFiles;

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function models(): iterable
    {
        $root = dirname(__DIR__, 3).'/app';
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // Only files that declare a class with a parent are autoloaded; reflection then
            // decides whether it is a model, so a custom base class or a fully qualified
            // parent cannot hide one
            if (preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*class\s+\w+\s+extends\s+\\\\?[\w\\\\]+/m', self::readFile($file->getPathname())) !== 1) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($root) + 1, -4);
            $class = 'App\\'.str_replace('/', '\\', $relative);

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            yield $class => [$class];
        }
    }

    /**
     * @param  class-string  $class
     */
    #[DataProvider('models')]
    public function test_every_core_model_uses_the_trait(string $class): void
    {
        $this->assertContains(StoresInstantsInStorageTimezone::class, class_uses_recursive($class), $class);
    }
}
