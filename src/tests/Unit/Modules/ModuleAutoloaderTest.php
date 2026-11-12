<?php

declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Modules\ModuleAutoloader;
use Closure;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;

/**
 * The SPL autoloader stack belongs to the process, not to an application: an autoloader
 * registered on every boot piles up for good, and one that captures an object keeps it
 * (and the application it points to) alive for the rest of the process.
 */
final class ModuleAutoloaderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/module-autoloader-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/src', 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_registering_a_namespace_and_path_twice_adds_one_autoloader(): void
    {
        $before = count(spl_autoload_functions());

        ModuleAutoloader::register('Modules\\AutoloadTwice', $this->root.'/src');
        ModuleAutoloader::register('Modules\\AutoloadTwice\\', $this->root.'/src/');

        $this->assertCount($before + 1, spl_autoload_functions());
    }

    public function test_the_autoloader_keeps_no_object_alive(): void
    {
        $before = spl_autoload_functions();

        ModuleAutoloader::register('Modules\\AutoloadNoObject', $this->root.'/src');

        $added = array_values(array_filter(
            spl_autoload_functions(),
            static fn (callable $autoloader): bool => ! in_array($autoloader, $before, true),
        ));

        $this->assertCount(1, $added);
        $this->assertInstanceOf(Closure::class, $added[0]);

        $closure = new ReflectionFunction($added[0]);
        $this->assertNull($closure->getClosureThis());

        foreach ($closure->getStaticVariables() as $value) {
            $this->assertIsScalar($value);
        }
    }

    public function test_it_loads_a_class_from_the_module_path(): void
    {
        $namespace = 'Modules\\AutoloadLoads'.bin2hex(random_bytes(4));
        mkdir($this->root.'/src/Support');
        file_put_contents($this->root.'/src/Support/Example.php', <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace}\\Support;

            final class Example {}
            PHP);

        ModuleAutoloader::register($namespace, $this->root.'/src');

        $this->assertTrue(class_exists($namespace.'\\Support\\Example'));
        $this->assertFalse(class_exists($namespace.'\\Support\\Missing'));
        $this->assertFalse(class_exists('Modules\\Other'.bin2hex(random_bytes(4)).'\\Support\\Example'));
    }

    public function test_the_same_namespace_in_another_path_gets_its_own_autoloader(): void
    {
        $before = count(spl_autoload_functions());

        ModuleAutoloader::register('Modules\\AutoloadMoved', $this->root.'/src');
        ModuleAutoloader::register('Modules\\AutoloadMoved', $this->root.'/elsewhere/src');

        $this->assertCount($before + 2, spl_autoload_functions());
    }

    public function test_no_other_core_code_registers_autoloaders(): void
    {
        $app = dirname(__DIR__, 3).'/app';
        $offenders = [];

        foreach ((new Filesystem)->allFiles($app) as $file) {
            if ($file->getExtension() !== 'php' || $file->getRealPath() === realpath($app.'/Modules/ModuleAutoloader.php')) {
                continue;
            }

            if (str_contains($file->getContents(), 'spl_autoload_register(')) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Register module autoloaders through ModuleAutoloader::register().');
    }
}
