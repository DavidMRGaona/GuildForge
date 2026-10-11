<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Services;

use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Modules\Services\ModuleScaffoldingService;
use App\Infrastructure\Modules\Services\StubRenderer;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The Filament classes `module:make-*` generates must load on the installed Filament:
 * PHP checks their signatures and properties against Filament's when the class is declared.
 */
final class ScaffoldedFilamentClassesTest extends TestCase
{
    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-scaffold-'.uniqid();
        File::ensureDirectoryExists($this->modulesPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_generated_resources_relation_managers_and_widgets_load(): void
    {
        $coreVersion = $this->createStub(CoreVersionServiceInterface::class);
        $coreVersion->method('getCurrentVersion')->willReturn(ModuleVersion::fromString('3.0.0'));
        $service = new ModuleScaffoldingService(new StubRenderer(base_path('stubs/modules')), $this->modulesPath, $coreVersion);

        $service->createModule('blog');
        $service->createFilamentResource('blog', 'Post');
        $service->createRelationManager('blog', 'Comments', 'Event');
        $service->createWidget('blog', 'Stats');

        $files = File::allFiles($this->modulesPath.'/blog/src/Filament');
        $this->assertCount(6, $files);

        foreach ($files as $file) {
            $process = new Process([
                (string) (new PhpExecutableFinder())->find(false),
                '-r',
                'require $argv[1]; require $argv[2];',
                base_path('vendor/autoload.php'),
                $file->getPathname(),
            ]);
            $process->run();

            $this->assertTrue($process->isSuccessful(), $file->getRelativePathname().': '.$process->getErrorOutput().$process->getOutput());
        }
    }
}
