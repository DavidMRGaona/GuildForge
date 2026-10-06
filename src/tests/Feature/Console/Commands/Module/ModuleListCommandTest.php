<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ModuleListCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-module-list-'.uniqid();
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_it_lists_all_modules_with_their_compatibility(): void
    {
        $this->moduleOnDisk('test-module');
        $this->moduleOnDisk('future-module', ['core' => '^99.0']);
        ModuleModel::factory()->create(['name' => 'test-module', 'version' => '1.0.0', 'description' => 'Test module description', 'status' => 'enabled']);
        ModuleModel::factory()->create(['name' => 'future-module', 'version' => '2.0.0', 'description' => 'Future module description', 'status' => 'enabled']);
        ModuleModel::factory()->create(['name' => 'missing-module', 'version' => '1.0.0', 'description' => 'Missing module description', 'status' => 'disabled']);

        $this->artisan('module:list')
            ->expectsTable(
                ['Name', 'Version', 'Status', 'Compatible', 'Description'],
                [
                    ['test-module', '1.0.0', 'enabled', 'sí', 'Test module description'],
                    ['future-module', '2.0.0', 'enabled', 'no: requiere core ^99.0', 'Future module description'],
                    ['missing-module', '1.0.0', 'disabled', 'no: no se encuentra module.json', 'Missing module description'],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_it_displays_empty_message_when_no_modules(): void
    {
        $this->artisan('module:list')
            ->expectsOutput('No modules found.')
            ->assertExitCode(0);
    }

    /**
     * @param  array<string, mixed>  $requires
     */
    private function moduleOnDisk(string $name, array $requires = []): void
    {
        File::ensureDirectoryExists("{$this->modulesPath}/{$name}");
        File::put("{$this->modulesPath}/{$name}/module.json", (string) json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'namespace' => 'Modules\\'.str_replace('-', '', ucwords($name, '-')),
            'provider' => str_replace('-', '', ucwords($name, '-')).'ServiceProvider',
            'requires' => $requires,
        ]));
    }
}
