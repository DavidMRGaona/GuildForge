<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Modules\Services;

use App\Infrastructure\Modules\Services\ModuleContextService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;
use Tests\TestCase;

final class ModuleContextServiceViewTest extends TestCase
{
    private string $viewsPath;

    private ModuleContextService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewsPath = sys_get_temp_dir().'/guildforge-test-module-views-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->viewsPath);
        File::put($this->viewsPath.'/greeting.blade.php', 'Hola {{ $name }}');

        // ModuleServiceProvider::loadViews() registers the kebab-case module name
        View::addNamespace('sample-module', $this->viewsPath);

        $this->service = $this->app->make(ModuleContextService::class);
        $this->service->setCurrent('sample-module');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->viewsPath);

        parent::tearDown();
    }

    public function test_it_renders_a_view_from_the_current_module_namespace(): void
    {
        $view = $this->service->view('greeting', ['name' => 'Ana']);

        $this->assertSame('sample-module::greeting', $view->name());
        $this->assertSame('Hola Ana', $view->render());
    }

    public function test_it_throws_when_the_module_view_does_not_exist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('View [sample-module::missing] not found.');

        $this->service->view('missing');
    }

    public function test_it_still_finds_a_view_under_the_former_snake_case_namespace(): void
    {
        $legacy = sys_get_temp_dir().'/guildforge-test-module-views-legacy-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($legacy);
        File::put($legacy.'/farewell.blade.php', 'Adiós');
        View::addNamespace('legacy_module', $legacy);
        $this->service->setCurrent('legacy-module');

        try {
            $this->assertSame('legacy_module::farewell', $this->service->view('farewell')->name());
        } finally {
            File::deleteDirectory($legacy);
        }
    }
}
