<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class ModuleEnableCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_enables_a_disabled_module(): void
    {
        $module = ModuleModel::factory()->disabled()->create([
            'name' => 'test-module',
        ]);

        $this->artisan('module:enable', ['module' => 'test-module'])
            ->expectsOutput('Module "test-module" has been enabled.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('modules', [
            'id' => $module->id,
            'name' => 'test-module',
            'status' => 'enabled',
        ]);

        $module->refresh();
        $this->assertNotNull($module->enabled_at);
    }

    public function test_it_fails_when_module_not_found(): void
    {
        $this->artisan('module:enable', ['module' => 'non-existent-module'])
            ->expectsOutput('Module "non-existent-module" not found.')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_module_already_enabled(): void
    {
        ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
        ]);

        $this->artisan('module:enable', ['module' => 'test-module'])
            ->expectsOutput('Module "test-module" is already enabled.')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_dependencies_not_satisfied(): void
    {
        // Create a module with dependencies
        ModuleModel::factory()->disabled()->create([
            'name' => 'dependent-module',
            'dependencies' => ['required-module'],
        ]);

        $this->artisan('module:enable', ['module' => 'dependent-module'])
            ->expectsOutput('Cannot enable module "dependent-module". Missing dependencies: required-module')
            ->assertExitCode(1);

        $this->assertDatabaseHas('modules', [
            'name' => 'dependent-module',
            'status' => 'disabled',
        ]);
    }
}
