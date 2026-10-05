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

        $this->viewsPath = sys_get_temp_dir().'/guildforge-test-module-views-'.uniqid();
        File::ensureDirectoryExists($this->viewsPath);
        File::put($this->viewsPath.'/greeting.blade.php', 'Hola {{ $name }}');

        View::addNamespace('sample_module', $this->viewsPath);

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

        $this->assertSame('sample_module::greeting', $view->name());
        $this->assertSame('Hola Ana', $view->render());
    }

    public function test_it_throws_when_the_module_view_does_not_exist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('View [sample_module::missing] not found.');

        $this->service->view('missing');
    }
}
