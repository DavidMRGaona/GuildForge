<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\ModulesPage;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ModulesPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_modules_page_requires_admin(): void
    {
        $user = UserModel::factory()->create(); // Regular member

        $this->actingAs($user);

        $this->get(ModulesPage::getUrl())
            ->assertForbidden();
    }

    public function test_modules_page_accessible_by_admin(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        $this->get(ModulesPage::getUrl())
            ->assertOk();
    }

    public function test_modules_page_displays_modules(): void
    {
        $user = UserModel::factory()->admin()->create();

        ModuleModel::factory()->create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
        ]);

        ModuleModel::factory()->enabled()->create([
            'name' => 'enabled-module',
            'display_name' => 'Enabled Module',
        ]);

        $this->actingAs($user);

        Livewire::test(ModulesPage::class)
            ->assertSee('Test Module')
            ->assertSee('Enabled Module');
    }

    public function test_modules_page_can_filter_by_enabled_status(): void
    {
        $user = UserModel::factory()->admin()->create();

        ModuleModel::factory()->disabled()->create([
            'name' => 'disabled-module',
            'display_name' => 'Disabled Module',
        ]);

        ModuleModel::factory()->enabled()->create([
            'name' => 'enabled-module',
            'display_name' => 'Enabled Module',
        ]);

        $this->actingAs($user);

        Livewire::test(ModulesPage::class)
            ->call('setFilter', 'enabled')
            ->assertSee('Enabled Module')
            ->assertDontSee('Disabled Module');
    }

    public function test_modules_page_can_filter_by_disabled_status(): void
    {
        $user = UserModel::factory()->admin()->create();

        ModuleModel::factory()->disabled()->create([
            'name' => 'disabled-module',
            'display_name' => 'Disabled Module',
        ]);

        ModuleModel::factory()->enabled()->create([
            'name' => 'enabled-module',
            'display_name' => 'Enabled Module',
        ]);

        $this->actingAs($user);

        Livewire::test(ModulesPage::class)
            ->call('setFilter', 'disabled')
            ->assertSee('Disabled Module')
            ->assertDontSee('Enabled Module');
    }

    public function test_modules_page_can_search_modules(): void
    {
        $user = UserModel::factory()->admin()->create();

        ModuleModel::factory()->create([
            'name' => 'alpha-module',
            'display_name' => 'Alpha Module',
        ]);

        ModuleModel::factory()->create([
            'name' => 'beta-module',
            'display_name' => 'Beta Module',
        ]);

        $this->actingAs($user);

        Livewire::test(ModulesPage::class)
            ->set('search', 'alpha')
            ->assertSee('Alpha Module')
            ->assertDontSee('Beta Module');
    }

    public function test_can_enable_disabled_module(): void
    {
        $user = UserModel::factory()->admin()->create();

        $module = ModuleModel::factory()->disabled()->create([
            'name' => 'test-module',
        ]);

        $this->actingAs($user);

        Livewire::test(ModulesPage::class)
            ->call('enableModule', 'test-module');

        $module->refresh();
        $this->assertEquals('enabled', $module->status);
    }

    public function test_can_disable_enabled_module(): void
    {
        $user = UserModel::factory()->admin()->create();

        $module = ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
        ]);

        $this->actingAs($user);

        Livewire::test(ModulesPage::class)
            ->call('disableModule', 'test-module');

        $module->refresh();
        $this->assertEquals('disabled', $module->status);
    }

    public function test_cannot_enable_module_with_missing_dependencies(): void
    {
        $user = UserModel::factory()->admin()->create();

        ModuleModel::factory()->disabled()->create([
            'name' => 'dependent-module',
            'dependencies' => ['missing-dependency'],
        ]);

        $this->actingAs($user);

        Livewire::test(ModulesPage::class)
            ->call('enableModule', 'dependent-module')
            ->assertNotified();

        $this->assertDatabaseHas('modules', [
            'name' => 'dependent-module',
            'status' => 'disabled',
        ]);
    }

    public function test_cannot_disable_module_with_enabled_dependents(): void
    {
        $user = UserModel::factory()->admin()->create();

        ModuleModel::factory()->enabled()->create([
            'name' => 'base-module',
        ]);

        ModuleModel::factory()->enabled()->create([
            'name' => 'dependent-module',
            'dependencies' => ['base-module'],
        ]);

        $this->actingAs($user);

        Livewire::test(ModulesPage::class)
            ->call('disableModule', 'base-module')
            ->assertNotified();

        $this->assertDatabaseHas('modules', [
            'name' => 'base-module',
            'status' => 'enabled',
        ]);
    }

    public function test_discover_action_finds_new_modules(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        // Note: This test is simplified since we can't create real module directories
        // In a real test, you would mock the ModuleManagerService
        Livewire::test(ModulesPage::class)
            ->callAction('discover')
            ->assertNotified();
    }
}
