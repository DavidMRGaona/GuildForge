<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Module;

use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ModuleListCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_all_modules(): void
    {
        ModuleModel::factory()->create([
            'name' => 'test-module',
            'version' => '1.0.0',
            'description' => 'Test module description',
            'status' => 'enabled',
        ]);

        ModuleModel::factory()->create([
            'name' => 'another-module',
            'version' => '2.0.0',
            'description' => 'Another module description',
            'status' => 'disabled',
        ]);

        $this->artisan('module:list')
            ->expectsTable(
                ['Name', 'Version', 'Status', 'Description'],
                [
                    ['test-module', '1.0.0', 'enabled', 'Test module description'],
                    ['another-module', '2.0.0', 'disabled', 'Another module description'],
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

    public function test_it_shows_module_status(): void
    {
        ModuleModel::factory()->enabled()->create([
            'name' => 'enabled-module',
            'version' => '1.0.0',
            'description' => 'Enabled module description',
        ]);

        ModuleModel::factory()->disabled()->create([
            'name' => 'disabled-module',
            'version' => '1.0.0',
            'description' => 'Disabled module description',
        ]);

        $this->artisan('module:list')
            ->expectsTable(
                ['Name', 'Version', 'Status', 'Description'],
                [
                    ['enabled-module', '1.0.0', 'enabled', 'Enabled module description'],
                    ['disabled-module', '1.0.0', 'disabled', 'Disabled module description'],
                ]
            )
            ->assertExitCode(0);
    }
}
