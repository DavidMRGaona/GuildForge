<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ModuleDisableCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_disables_an_enabled_module(): void
    {
        $module = ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
        ]);

        $this->artisan('module:disable', ['module' => 'test-module'])
            ->expectsOutput('Module "test-module" has been disabled.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('modules', [
            'id' => $module->id,
            'name' => 'test-module',
            'status' => 'disabled',
        ]);

        $module->refresh();
        $this->assertNull($module->enabled_at);
    }

    public function test_it_fails_when_module_not_found(): void
    {
        $this->artisan('module:disable', ['module' => 'non-existent-module'])
            ->expectsOutput('Module "non-existent-module" not found.')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_module_already_disabled(): void
    {
        ModuleModel::factory()->disabled()->create([
            'name' => 'test-module',
        ]);

        $this->artisan('module:disable', ['module' => 'test-module'])
            ->expectsOutput('Module "test-module" is already disabled.')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_dependents_exist(): void
    {
        // Create a module that is required by another
        ModuleModel::factory()->enabled()->create([
            'name' => 'required-module',
        ]);

        ModuleModel::factory()->enabled()->create([
            'name' => 'dependent-module',
            'dependencies' => ['required-module'],
        ]);

        $this->artisan('module:disable', ['module' => 'required-module'])
            ->expectsOutput('Cannot disable module "required-module". It is required by: dependent-module')
            ->assertExitCode(1);

        $this->assertDatabaseHas('modules', [
            'name' => 'required-module',
            'status' => 'enabled',
        ]);
    }
}
