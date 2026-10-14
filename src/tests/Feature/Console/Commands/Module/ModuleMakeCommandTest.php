<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ModuleMakeCommandTest extends TestCase
{
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
        $this->artisan('module:make', [
            'name' => 'blog-posts',
            '--display-name' => 'Entradas del blog',
            '--repository' => 'acme/guildforge-blog-posts',
        ])->assertSuccessful();

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) file_get_contents($this->modulesPath.'/blog-posts/module.json'), true);

        $this->assertSame('Entradas del blog', $manifest['displayName']);
        $this->assertSame('acme/guildforge-blog-posts', $manifest['repository']);
    }

    public function test_it_lists_the_steps_a_new_module_still_needs(): void
    {
        $this->artisan('module:make', ['name' => 'blog-posts'])
            ->expectsOutputToContain('module:discover')
            ->expectsOutputToContain('module:enable blog-posts')
            ->expectsOutputToContain('module:set-source blog-posts')
            ->expectsOutputToContain('module:build blog-posts')
            ->assertSuccessful();
    }

    public function test_it_refuses_a_name_whose_page_prefix_is_a_core_page(): void
    {
        $this->artisan('module:make', ['name' => 'events'])
            ->expectsOutputToContain('page prefix is taken')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->modulesPath.'/events');
    }
}
