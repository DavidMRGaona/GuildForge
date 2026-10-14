<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Services;

use App\Infrastructure\Modules\Services\ModuleContextService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Module helpers read what ModuleServiceProvider registers: translations under the
 * kebab-case module name and config/module.php under "modules.<name>".
 */
final class ModuleContextServiceNamespaceTest extends TestCase
{
    private string $langPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->langPath = sys_get_temp_dir().'/guildforge-test-module-lang-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->langPath.'/es');
        File::put($this->langPath.'/es/messages.php', "<?php return ['title' => 'Entradas del blog'];");
        $this->app['translator']->addNamespace('blog-posts', $this->langPath);
        $this->app->setLocale('es');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->langPath);

        parent::tearDown();
    }

    public function test_module_trans_reads_the_kebab_case_namespace_of_a_hyphenated_module(): void
    {
        $this->assertSame('Entradas del blog', module_trans('blog-posts', 'messages.title'));
    }

    public function test_module_config_reads_the_config_the_provider_loads(): void
    {
        config(['modules.blog-posts' => ['enabled' => true, 'per_page' => 12]]);

        $this->assertSame(12, module_config('blog-posts', 'per_page'));
        $this->assertTrue(module_config('blog-posts', 'enabled'));
        $this->assertSame('x', module_config('blog-posts', 'missing', 'x'));
    }

    public function test_module_config_still_reads_a_config_merged_under_the_former_snake_case_key(): void
    {
        config(['blog_posts' => ['per_page' => 30]]);

        $this->assertSame(30, module_config('blog-posts', 'per_page'));
    }

    public function test_the_current_module_config_uses_the_same_lookup(): void
    {
        config(['modules.blog-posts' => ['per_page' => 12]]);
        $service = $this->app->make(ModuleContextService::class);
        $service->setCurrent('blog-posts');

        $this->assertSame(12, $service->config('per_page'));
    }
}
