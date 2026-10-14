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
 * Every file a module:make* command writes must be usable as written: valid PHP,
 * with every stub placeholder replaced.
 */
final class ScaffoldedModuleFilesTest extends TestCase
{
    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-scaffold-files-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->modulesPath);

        $coreVersion = $this->createStub(CoreVersionServiceInterface::class);
        $coreVersion->method('getCurrentVersion')->willReturn(ModuleVersion::fromString('3.6.0'));
        $service = new ModuleScaffoldingService(new StubRenderer(base_path('stubs/modules')), $this->modulesPath, $coreVersion);

        $service->createModule('blog-posts', 'Entradas del blog', 'GuildForge');
        $service->createEntity('blog-posts', 'Post');
        $service->createDto('blog-posts', 'Post');
        $service->createDto('blog-posts', 'Post', true);
        $service->createService('blog-posts', 'Post');
        $service->createService('blog-posts', 'Counter', false);
        $service->createRequest('blog-posts', 'Post');
        $service->createController('blog-posts', 'Post', 'resource');
        $service->createController('blog-posts', 'Post', 'api');
        $service->createController('blog-posts', 'Archive');
        $service->createController('blog-posts', 'Feed', 'invokable');
        $service->createFilamentResource('blog-posts', 'Post');
        $service->createRelationManager('blog-posts', 'Comments', 'Event');
        $service->createWidget('blog-posts', 'Stats');
        $service->createEnum('blog-posts', 'PostStatus');
        $service->createDomainEvent('blog-posts', 'PostPublished');
        $service->createListener('blog-posts', 'NotifyAuthor', 'PostPublished');
        $service->createListener('blog-posts', 'IndexPost', 'PostPublished', true);
        $service->createNotification('blog-posts', 'PostPublishedNotification');
        $service->createMigration('blog-posts', 'add_slug_to_posts_table', 'blog_posts_posts', true);
        $service->createTest('blog-posts', 'Post', 'unit');
        $service->createTest('blog-posts', 'Archive', 'feature');
        $service->createVuePage('blog-posts', 'Archive');
        $service->createVueComponent('blog-posts', 'PostCard');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_every_generated_php_file_is_valid_php(): void
    {
        $files = array_filter(
            File::allFiles($this->modulesPath.'/blog-posts'),
            fn (\SplFileInfo $file): bool => $file->getExtension() === 'php',
        );
        $this->assertGreaterThan(30, count($files));

        foreach ($files as $file) {
            $process = new Process([(string) (new PhpExecutableFinder)->find(false), '-l', $file->getPathname()]);
            $process->run();

            $this->assertTrue($process->isSuccessful(), $file->getPathname().': '.$process->getOutput().$process->getErrorOutput());
        }
    }

    public function test_no_stub_placeholder_is_left_in_any_generated_file(): void
    {
        foreach (File::allFiles($this->modulesPath.'/blog-posts', true) as $file) {
            $content = $file->getContents();

            if ($file->getExtension() === 'vue') {
                // Vue templates use mustaches of their own; the old escape attempt must be gone
                $this->assertStringNotContainsString("{{ '{{' }}", $content, $file->getRelativePathname());
                $this->assertDoesNotMatchRegularExpression('/\{\{ ?(name|module)[A-Za-z]* ?\}\}/', $content, $file->getRelativePathname());

                continue;
            }

            $this->assertDoesNotMatchRegularExpression('/\{\{ ?[A-Za-z]+ ?\}\}/', $content, $file->getRelativePathname());
        }
    }
}
