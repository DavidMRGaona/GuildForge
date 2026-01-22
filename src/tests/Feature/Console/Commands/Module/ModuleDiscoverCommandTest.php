<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ModuleDiscoverCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->modulesPath = base_path('modules');
    }

    protected function tearDown(): void
    {
        // Clean up test modules
        if (File::exists($this->modulesPath . '/test-discovery-module')) {
            File::deleteDirectory($this->modulesPath . '/test-discovery-module');
        }
        if (File::exists($this->modulesPath . '/another-discovery-module')) {
            File::deleteDirectory($this->modulesPath . '/another-discovery-module');
        }

        parent::tearDown();
    }

    public function test_it_discovers_modules_from_filesystem(): void
    {
        // Create test module directory with module.json
        $modulePath = $this->modulesPath . '/test-discovery-module';
        File::makeDirectory($modulePath, 0755, true);
        File::put($modulePath . '/module.json', json_encode([
            'name' => 'test-discovery-module',
            'version' => '1.0.0',
            'description' => 'Test discovery module',
            'author' => 'Test Author',
            'namespace' => 'Modules\\TestDiscoveryModule',
            'provider' => 'TestDiscoveryModuleServiceProvider',
        ]));

        $this->artisan('module:discover')
            ->expectsOutput('Discovering modules...')
            ->expectsOutput('Found 1 module(s).')
            ->assertExitCode(0);

        $this->assertDatabaseHas('modules', [
            'name' => 'test-discovery-module',
            'version' => '1.0.0',
        ]);
    }

    public function test_it_displays_message_when_no_modules_found(): void
    {
        // Ensure modules directory is empty or doesn't contain valid modules
        $this->artisan('module:discover')
            ->expectsOutput('Discovering modules...')
            ->expectsOutput('No modules found.')
            ->assertExitCode(0);
    }

    public function test_it_shows_count_of_discovered_modules(): void
    {
        // Create multiple test modules
        $modules = ['test-discovery-module', 'another-discovery-module'];

        foreach ($modules as $moduleName) {
            $modulePath = $this->modulesPath . '/' . $moduleName;
            File::makeDirectory($modulePath, 0755, true);
            File::put($modulePath . '/module.json', json_encode([
                'name' => $moduleName,
                'version' => '1.0.0',
                'description' => "Test {$moduleName}",
                'author' => 'Test Author',
                'namespace' => 'Modules\\' . str_replace('-', '', ucwords($moduleName, '-')),
                'provider' => str_replace('-', '', ucwords($moduleName, '-')) . 'ServiceProvider',
            ]));
        }

        $this->artisan('module:discover')
            ->expectsOutput('Discovering modules...')
            ->expectsOutput('Found 2 module(s).')
            ->assertExitCode(0);
    }
}
