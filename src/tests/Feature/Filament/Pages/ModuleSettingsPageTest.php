<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\ModuleSettingsPage;
use App\Filament\Pages\ModulesPage;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ModuleSettingsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_settings_page_requires_admin(): void
    {
        $user = UserModel::factory()->create(); // Regular member

        ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
        ]);

        $this->actingAs($user);

        $this->get(ModuleSettingsPage::getUrl(['module' => 'test-module']))
            ->assertForbidden();
    }

    public function test_settings_page_accessible_by_admin(): void
    {
        $user = UserModel::factory()->admin()->create();

        ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
        ]);

        $this->actingAs($user);

        $this->get(ModuleSettingsPage::getUrl(['module' => 'test-module']))
            ->assertOk();
    }

    public function test_settings_page_shows_module_info(): void
    {
        $user = UserModel::factory()->admin()->create();

        ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'version' => '1.0.0',
            'author' => 'Test Author',
            'description' => 'A test module description',
        ]);

        $this->actingAs($user);

        Livewire::test(ModuleSettingsPage::class, ['module' => 'test-module'])
            ->assertSee('Test Module')
            ->assertSee('1.0.0')
            ->assertSee('Test Author');
    }

    public function test_redirects_if_module_not_found(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->actingAs($user);

        Livewire::test(ModuleSettingsPage::class, ['module' => 'non-existent-module'])
            ->assertRedirect(ModulesPage::getUrl());
    }

    public function test_redirects_if_module_disabled(): void
    {
        $user = UserModel::factory()->admin()->create();

        ModuleModel::factory()->disabled()->create([
            'name' => 'disabled-module',
        ]);

        $this->actingAs($user);

        Livewire::test(ModuleSettingsPage::class, ['module' => 'disabled-module'])
            ->assertRedirect(ModulesPage::getUrl());
    }

    public function test_shows_no_settings_message_when_module_has_no_settings(): void
    {
        $user = UserModel::factory()->admin()->create();

        ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
            'provider' => 'NonExistentProvider',
        ]);

        $this->actingAs($user);

        Livewire::test(ModuleSettingsPage::class, ['module' => 'test-module'])
            ->assertSee(__('modules.filament.settings_page.no_settings'));
    }

    public function test_breadcrumbs_include_modules_page(): void
    {
        $user = UserModel::factory()->admin()->create();

        ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
        ]);

        $this->actingAs($user);

        $component = Livewire::test(ModuleSettingsPage::class, ['module' => 'test-module']);

        $breadcrumbs = $component->instance()->getBreadcrumbs();

        $this->assertArrayHasKey(ModulesPage::getUrl(), $breadcrumbs);
    }
}
