<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Updates\Services;

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

        $this->modulesPath = sys_get_temp_dir().'/gf-installer-'.uniqid();
        File::makeDirectory($this->modulesPath, 0775, true);
        config(['modules.path' => $this->modulesPath]);

        $this->installer = new ModulePackageInstaller;
        $this->makeModule('game-tables', '1.0.0');
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
        $zip = $this->releaseZip('game-tables', '1.1.0');

        $previous = $this->installer->swap($this->installer->stage($zip, 'game-tables'), 'game-tables');

        $this->assertSame('1.1.0', $this->manifestVersion('game-tables'));
        $this->assertFileExists($this->modulesPath.'/game-tables/public/build/manifest.json');
        $this->assertSame('1.0.0', json_decode(File::get($previous.'/module.json'), true)['version']);
    }

    public function test_swap_never_touches_sibling_modules(): void
    {
        $zip = $this->releaseZip('game-tables', '1.1.0');

        $this->installer->swap($this->installer->stage($zip, 'game-tables'), 'game-tables');

        $this->assertSame('channel-notifications', json_decode(File::get($this->modulesPath.'/channel-notifications/module.json'), true)['name']);
        $this->assertSame([], glob($this->modulesPath.'/.staging-*', GLOB_ONLYDIR));
    }

    public function test_revert_restores_previous_version(): void
    {
        $zip = $this->releaseZip('game-tables', '1.1.0');
        $previous = $this->installer->swap($this->installer->stage($zip, 'game-tables'), 'game-tables');

        $this->installer->revert($previous, 'game-tables');

        $this->assertSame('1.0.0', $this->manifestVersion('game-tables'));
        $this->assertDirectoryDoesNotExist($previous);
    }

    public function test_discard_removes_the_previous_version(): void
    {
        $zip = $this->releaseZip('game-tables', '1.1.0');
        $previous = $this->installer->swap($this->installer->stage($zip, 'game-tables'), 'game-tables');

        $this->installer->discard($previous);

        $this->assertDirectoryDoesNotExist($previous);
        $this->assertSame('1.1.0', $this->manifestVersion('game-tables'));
    }

    /**
     * @return iterable<string, array{0: array<string, string>}>
     */
    public static function invalidPackages(): iterable
    {
        yield 'other module' => [['tournaments-1.0.0/module.json' => '{"name":"tournaments","version":"1.0.0"}']];
        yield 'two roots' => [['a/module.json' => '{"name":"game-tables"}', 'b/x.txt' => 'x']];
        yield 'no manifest' => [['game-tables-1.1.0/readme.md' => 'x']];
        yield 'zip slip' => [['game-tables-1.1.0/module.json' => '{"name":"game-tables"}', '../evil.php' => '<?php']];
    }

    /**
     * @param  array<string, string>  $entries
     */
    #[DataProvider('invalidPackages')]
    public function test_stage_rejects_invalid_packages_without_touching_the_module(array $entries): void
    {
        $zip = $this->zipWith($entries);

        try {
            $this->installer->stage($zip, 'game-tables');
            $this->fail('Expected UpdateException');
        } catch (UpdateException) {
        }

        $this->assertSame('1.0.0', $this->manifestVersion('game-tables'));
        $this->assertSame([], glob($this->modulesPath.'/.staging-*', GLOB_ONLYDIR));
        $this->assertFileDoesNotExist(dirname($this->modulesPath).'/evil.php');
    }

    public function test_stage_rejects_an_unreadable_zip(): void
    {
        $path = $this->modulesPath.'/../broken-'.uniqid().'.zip';
        File::put($path, 'not a zip');

        $this->expectException(UpdateException::class);

        try {
            $this->installer->stage($path, 'game-tables');
        } finally {
            File::delete($path);
        }
    }

    public function test_cleanup_leftovers_removes_only_this_modules_temp_dirs(): void
    {
        File::makeDirectory($this->modulesPath.'/.staging-game-tables-old');
        File::makeDirectory($this->modulesPath.'/.previous-game-tables-old');
        File::makeDirectory($this->modulesPath.'/.staging-tournaments-old');

        $this->installer->cleanupLeftovers('game-tables');

        $this->assertDirectoryDoesNotExist($this->modulesPath.'/.staging-game-tables-old');
        $this->assertDirectoryDoesNotExist($this->modulesPath.'/.previous-game-tables-old');
        $this->assertDirectoryExists($this->modulesPath.'/.staging-tournaments-old');
        $this->assertDirectoryExists($this->modulesPath.'/game-tables');
    }

    private function makeModule(string $name, string $version): void
    {
        File::makeDirectory($this->modulesPath."/{$name}/src", 0775, true);
        File::put($this->modulesPath."/{$name}/module.json", json_encode(['name' => $name, 'version' => $version]));
        File::put($this->modulesPath."/{$name}/src/Dummy.php", '<?php');
    }

    private function releaseZip(string $name, string $version): string
    {
        return $this->zipWith([
            "{$name}-{$version}/module.json" => (string) json_encode(['name' => $name, 'version' => $version]),
            "{$name}-{$version}/src/Dummy.php" => '<?php',
            "{$name}-{$version}/public/build/manifest.json" => '{}',
        ]);
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function zipWith(array $entries): string
    {
        $path = sys_get_temp_dir().'/gf-release-'.uniqid().'.zip';
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
