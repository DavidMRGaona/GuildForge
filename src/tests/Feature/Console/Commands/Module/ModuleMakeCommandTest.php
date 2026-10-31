<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\Support\Files\ReadsFiles;
use Tests\TestCase;

final class ModuleMakeCommandTest extends TestCase
{
    use ReadsFiles;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/make-command-'.bin2hex(random_bytes(8)));
        File::makeDirectory($this->modulesPath, 0755, true, true);
        config(['modules.path' => $this->modulesPath]);
        $this->app->forgetInstance(ModuleScaffoldingServiceInterface::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_it_has_no_force_option_since_existing_modules_are_never_overwritten(): void
    {
        $definition = Artisan::all()['module:make']->getDefinition();

        $this->assertFalse($definition->hasOption('force'));
    }

    public function test_it_passes_the_display_name_and_repository_to_the_manifest(): void
    {
        $this->artisanCommand('module:make', [
            'name' => 'blog-posts',
            '--display-name' => 'Entradas del blog',
            '--repository' => 'acme/guildforge-blog-posts',
        ])->assertSuccessful();

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode(self::readFile($this->modulesPath.'/blog-posts/module.json'), true);

        $this->assertSame('Entradas del blog', $manifest['displayName']);
        $this->assertSame('acme/guildforge-blog-posts', $manifest['repository']);
    }

    public function test_it_passes_the_core_constraint_to_the_manifest(): void
    {
        $this->artisanCommand('module:make', ['name' => 'blog-posts', '--core' => '^3.30'])->assertSuccessful();

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode(self::readFile($this->modulesPath.'/blog-posts/module.json'), true);

        $this->assertSame('^3.30', $manifest['requires']['core']);
    }

    public function test_it_fills_a_cloned_repository_when_asked(): void
    {
        File::makeDirectory($this->modulesPath.'/blog-posts');
        File::put($this->modulesPath.'/blog-posts/README.md', "# Blog\n");

        $this->artisanCommand('module:make', ['name' => 'blog-posts', '--into-existing' => true])
            ->expectsOutputToContain('Kept the existing files: README.md')
            ->assertSuccessful();

        $this->assertSame("# Blog\n", self::readFile($this->modulesPath.'/blog-posts/README.md'));
        $this->assertFileExists($this->modulesPath.'/blog-posts/module.json');
    }

    public function test_it_lists_the_steps_a_new_module_still_needs(): void
    {
        $this->artisanCommand('module:make', ['name' => 'blog-posts'])
            ->expectsOutputToContain('module:discover')
            ->expectsOutputToContain('module:enable blog-posts')
            ->expectsOutputToContain('module:set-source blog-posts')
            ->expectsOutputToContain('guildforge_node')
            ->assertSuccessful();
    }

    public function test_it_refuses_a_name_whose_page_prefix_is_a_core_page(): void
    {
        $this->artisanCommand('module:make', ['name' => 'events'])
            ->expectsOutputToContain('page prefix is taken')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->modulesPath.'/events');
    }
}
