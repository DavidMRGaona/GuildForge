<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

use App\Domain\Modules\Exceptions\ModuleIncompatibleException;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Infrastructure\Updates\Services\ModulePackageInstaller;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

final class ModulePackageInstallerTest extends TestCase
{
    private string $modulesPath;

    private ModulePackageInstaller $installer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-installer-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->modulesPath, 0775, true);
        config(['modules.path' => $this->modulesPath]);

        $this->installer = $this->app->make(ModulePackageInstaller::class);
        $this->makeModule('sample-module', '1.0.0');
        $this->makeModule('channel-notifications', '1.0.0');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        File::delete(dirname($this->modulesPath).'/evil.php');
        parent::tearDown();
    }

    public function test_stage_and_swap_install_the_new_version(): void
    {
        $zip = $this->releaseZip('sample-module', '1.1.0');

        $previous = $this->installer->swap($this->installer->stage($zip, 'sample-module'), 'sample-module');

        $this->assertSame('1.1.0', $this->manifestVersion('sample-module'));
        $this->assertFileExists($this->modulesPath.'/sample-module/public/build/manifest.json');
        $this->assertSame('1.0.0', json_decode(File::get($previous.'/module.json'), true)['version']);
    }

    public function test_swap_never_touches_sibling_modules(): void
    {
        $zip = $this->releaseZip('sample-module', '1.1.0');

        $this->installer->swap($this->installer->stage($zip, 'sample-module'), 'sample-module');

        $this->assertSame('channel-notifications', json_decode(File::get($this->modulesPath.'/channel-notifications/module.json'), true)['name']);
        $this->assertSame([], glob($this->modulesPath.'/.staging-*', GLOB_ONLYDIR));
    }

    public function test_revert_restores_previous_version(): void
    {
        $zip = $this->releaseZip('sample-module', '1.1.0');
        $previous = $this->installer->swap($this->installer->stage($zip, 'sample-module'), 'sample-module');

        $this->installer->revert($previous, 'sample-module');

        $this->assertSame('1.0.0', $this->manifestVersion('sample-module'));
        $this->assertDirectoryDoesNotExist($previous);
    }

    public function test_discard_removes_the_previous_version(): void
    {
        $zip = $this->releaseZip('sample-module', '1.1.0');
        $previous = $this->installer->swap($this->installer->stage($zip, 'sample-module'), 'sample-module');

        $this->installer->discard($previous);

        $this->assertDirectoryDoesNotExist($previous);
        $this->assertSame('1.1.0', $this->manifestVersion('sample-module'));
    }

    /**
     * @return iterable<string, array{0: array<string, string>}>
     */
    public static function invalidPackages(): iterable
    {
        yield 'other module' => [['tournaments-1.0.0/module.json' => '{"name":"tournaments","version":"1.0.0"}']];
        yield 'two roots' => [['a/module.json' => '{"name":"sample-module"}', 'b/x.txt' => 'x']];
        yield 'no manifest' => [['sample-module-1.1.0/readme.md' => 'x']];
        yield 'zip slip' => [['sample-module-1.1.0/module.json' => '{"name":"sample-module"}', '../evil.php' => '<?php']];
    }

    /**
     * @param  array<string, string>  $entries
     */
    #[DataProvider('invalidPackages')]
    public function test_stage_rejects_invalid_packages_without_touching_the_module(array $entries): void
    {
        $zip = $this->zipWith($entries);

        try {
            $this->installer->stage($zip, 'sample-module');
            $this->fail('Expected UpdateException');
        } catch (UpdateException) {
        }

        $this->assertSame('1.0.0', $this->manifestVersion('sample-module'));
        $this->assertSame([], glob($this->modulesPath.'/.staging-*', GLOB_ONLYDIR));
        $this->assertFileDoesNotExist(dirname($this->modulesPath).'/evil.php');
    }

    public function test_stage_rejects_an_unreadable_zip(): void
    {
        $path = $this->modulesPath.'/../broken-'.bin2hex(random_bytes(8)).'.zip';
        File::put($path, 'not a zip');

        $this->expectException(UpdateException::class);

        try {
            $this->installer->stage($path, 'sample-module');
        } finally {
            File::delete($path);
        }
    }

    public function test_cleanup_leftovers_removes_only_this_modules_staging_dirs(): void
    {
        File::makeDirectory($this->modulesPath.'/.staging-sample-module-old');
        File::makeDirectory($this->modulesPath.'/.previous-sample-module-old');
        File::makeDirectory($this->modulesPath.'/.staging-tournaments-old');

        $this->installer->cleanupLeftovers('sample-module');

        $this->assertDirectoryDoesNotExist($this->modulesPath.'/.staging-sample-module-old');
        // A previous copy may be the only good version left after a failed revert
        $this->assertDirectoryExists($this->modulesPath.'/.previous-sample-module-old');
        $this->assertDirectoryExists($this->modulesPath.'/.staging-tournaments-old');
        $this->assertDirectoryExists($this->modulesPath.'/sample-module');
    }

    public function test_stage_rejects_an_incompatible_release_and_leaves_the_installed_module_untouched(): void
    {
        $zip = $this->zipWith([
            'sample-module-2.0.0/module.json' => $this->manifest('sample-module', '2.0.0', ['core' => '^99.0']),
            'sample-module-2.0.0/src/Dummy.php' => '<?php',
        ]);

        try {
            $this->installer->stage($zip, 'sample-module');
            $this->fail('Expected ModuleIncompatibleException');
        } catch (ModuleIncompatibleException $e) {
            $this->assertSame('2.0.0', $e->version);
            $this->assertStringContainsString('requires core ^99.0', $e->getMessage());
        }

        $this->assertSame('1.0.0', $this->manifestVersion('sample-module'));
        $this->assertSame([], glob($this->modulesPath.'/.staging-*', GLOB_ONLYDIR));
    }

    /**
     * @param  array<string, mixed>  $requires
     */
    private function manifest(string $name, string $version, array $requires = []): string
    {
        return json_encode([
            'name' => $name,
            'version' => $version,
            'storesUtc' => true,
            'namespace' => 'Modules\\'.str_replace('-', '', ucwords($name, '-')),
            'provider' => str_replace('-', '', ucwords($name, '-')).'ServiceProvider',
            'requires' => $requires + ['core' => '>=2.0'],
        ], JSON_THROW_ON_ERROR);
    }

    private function makeModule(string $name, string $version): void
    {
        File::makeDirectory($this->modulesPath."/{$name}/src", 0775, true);
        File::put($this->modulesPath."/{$name}/module.json", $this->manifest($name, $version));
        File::put($this->modulesPath."/{$name}/src/Dummy.php", '<?php');
    }

    private function releaseZip(string $name, string $version): string
    {
        return $this->zipWith([
            "{$name}-{$version}/module.json" => $this->manifest($name, $version),
            "{$name}-{$version}/src/Dummy.php" => '<?php',
            "{$name}-{$version}/public/build/manifest.json" => '{}',
        ]);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function zipWith(array $entries): string
    {
        $path = sys_get_temp_dir().'/gf-release-'.bin2hex(random_bytes(8)).'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $entry => $content) {
            $zip->addFromString($entry, $content);
        }

        $zip->close();

        return $path;
    }

    private function manifestVersion(string $name): string
    {
        return json_decode(File::get($this->modulesPath."/{$name}/module.json"), true)['version'];
    }
}
