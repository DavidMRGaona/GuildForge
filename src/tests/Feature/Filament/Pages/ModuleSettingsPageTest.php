<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\ModuleSettingsPage;
use App\Filament\Pages\ModulesPage;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Modules\ModuleServiceProvider;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Support\Modules\RegistersBootedModuleProviders;
use Tests\TestCase;

final class ModuleSettingsPageTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RegistersBootedModuleProviders;

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
        $this->loadTestModuleWithSettings();

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
        $this->loadTestModuleWithSettings();

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

    public function test_a_module_without_settings_goes_back_to_the_modules_page_with_a_notice(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        ModuleModel::factory()->enabled()->create(['name' => 'test-module', 'provider' => 'NonExistentProvider']);

        Livewire::test(ModuleSettingsPage::class, ['module' => 'test-module'])
            ->assertRedirect(ModulesPage::getUrl())
            ->assertNotified(__('modules.filament.settings_page.no_settings'));
    }

    public function test_saving_a_module_without_settings_leaves_its_settings_file_untouched(): void
    {
        // mount() sends such a module away, but Livewire still lets anyone call save() on a
        // component it has rendered before: the guard in save() is the defence of last resort
        $modulePath = sys_get_temp_dir().'/gf-no-settings-'.bin2hex(random_bytes(8));
        $settingsFile = $modulePath.'/config/settings.php';
        $original = "<?php\n\nreturn ['gtm_container_id' => ''];\n";
        File::ensureDirectoryExists(dirname($settingsFile));
        File::put($settingsFile, $original);
        ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
            'provider' => 'NonExistentProvider',
            'path' => $modulePath,
        ]);
        $this->actingAs(UserModel::factory()->admin()->create());

        try {
            $page = app(ModuleSettingsPage::class);
            $page->module = 'test-module';
            $page->save();

            $this->assertSame($original, File::get($settingsFile));
        } finally {
            File::deleteDirectory($modulePath);
        }
    }

    public function test_breadcrumbs_include_modules_page(): void
    {
        $user = UserModel::factory()->admin()->create();

        ModuleModel::factory()->enabled()->create([
            'name' => 'test-module',
            'display_name' => 'Test Module',
        ]);
        $this->loadTestModuleWithSettings();

        $this->actingAs($user);

        $component = Livewire::test(ModuleSettingsPage::class, ['module' => 'test-module']);

        $breadcrumbs = $component->instance()->getBreadcrumbs();

        $this->assertArrayHasKey(ModulesPage::getUrl(), $breadcrumbs);
    }

    private function loadTestModuleWithSettings(): void
    {
        $this->loadProvider(new class (app()) extends ModuleServiceProvider {
            public function moduleName(): string
            {
                return 'test-module';
            }

            public function getSettingsSchema(): array
            {
                return [TextInput::make('greeting')];
            }
        });
    }
}
