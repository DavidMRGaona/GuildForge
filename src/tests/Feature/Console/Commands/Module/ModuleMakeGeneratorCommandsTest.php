<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ModuleMakeGeneratorCommandsTest extends TestCase
{
    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = storage_path('framework/testing/make-generators-'.bin2hex(random_bytes(8)));
        File::makeDirectory($this->modulesPath, 0755, true, true);
        config(['modules.path' => $this->modulesPath]);
        $this->app->forgetInstance(ModuleScaffoldingServiceInterface::class);

        $this->artisan('module:make', ['name' => 'blog-posts'])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);

        parent::tearDown();
    }

    public function test_a_migration_with_the_table_option_modifies_that_table(): void
    {
        $this->artisan('module:make-migration', [
            'module' => 'blog-posts',
            'name' => 'add_slug_to_posts_table',
            '--table' => 'blog_posts_posts',
        ])->assertSuccessful();

        $content = (string) file_get_contents((string) collect(File::files($this->modulesPath.'/blog-posts/database/migrations'))
            ->first(fn (\SplFileInfo $file): bool => str_ends_with($file->getFilename(), '_add_slug_to_posts_table.php'))
            ?->getPathname());

        $this->assertStringContainsString("Schema::table('blog_posts_posts'", $content);
        $this->assertStringNotContainsString('dropIfExists', $content);
    }

    public function test_the_widget_command_points_to_the_provider_hook_that_adds_it_to_the_dashboard(): void
    {
        $this->artisan('module:make-widget', ['module' => 'blog-posts', 'name' => 'Stats'])
            ->expectsOutputToContain('registerFilamentWidgets')
            ->doesntExpectOutputToContain('Livewire::component')
            ->assertSuccessful();
    }

    public function test_the_resource_command_says_how_to_activate_its_policy_and_permissions(): void
    {
        $this->artisan('module:make-filament-resource', ['module' => 'blog-posts', 'name' => 'Post'])
            ->expectsOutputToContain('registerPolicies')
            ->expectsOutputToContain('registerPermissions')
            ->assertSuccessful();
    }

    public function test_the_controller_command_says_which_route_and_page_it_needs(): void
    {
        $this->artisan('module:make-controller', ['module' => 'blog-posts', 'name' => 'Post'])
            ->expectsOutputToContain("Route::get('posts', [PostController::class, 'index'])->name('posts.index');")
            ->expectsOutputToContain('module:make-vue-page blog-posts Post')
            ->assertSuccessful();
    }

    public function test_the_listener_command_says_how_to_register_it(): void
    {
        $this->artisan('module:make-listener', ['module' => 'blog-posts', 'name' => 'NotifyAuthor', '--event' => 'PostPublished'])
            ->expectsOutputToContain('Event::listen(PostPublished::class, NotifyAuthor::class)')
            ->assertSuccessful();
    }
}
