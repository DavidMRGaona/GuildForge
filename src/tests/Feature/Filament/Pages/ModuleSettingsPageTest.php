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
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\OwnClusterSettingsPage;
use Tests\Support\Modules\RegistersBootedModuleProviders;
use Tests\Support\Modules\SettingsPageModuleProvider;
use Tests\Support\Modules\UnregisteredClusterSettingsPage;
use Tests\TestCase;

final class ModuleSettingsPageTest extends TestCase
{
    use InteractsWithModulePanel;
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
            ->assertRedirect(ModulesPage::getUrl())
            ->assertNotified(__('modules.filament.settings_page.module_disabled'));
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

        $page = $component->instance();
        $this->assertInstanceOf(ModuleSettingsPage::class, $page);
        $breadcrumbs = $page->getBreadcrumbs();

        $this->assertArrayHasKey(ModulesPage::getUrl(), $breadcrumbs);
    }

    public function test_a_module_with_its_own_settings_page_is_sent_to_that_page(): void
    {
        // Old links keep working for whoever may open this page (modules.configure); the module's
        // page then checks its own permission
        ModuleModel::factory()->enabled()->create(['name' => 'test-module']);
        $this->registerOnAdminPanel(pages: [OwnClusterSettingsPage::class]);
        $this->registerBootedProvider(SettingsPageModuleProvider::owning(app(), 'test-module', OwnClusterSettingsPage::class));
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(ModuleSettingsPage::getUrl(['module' => 'test-module']))
            ->assertRedirect(OwnClusterSettingsPage::getUrl());
    }

    /**
     * @return array<string, array{\Closure(): SettingsPageModuleProvider}>
     */
    public static function modulesWithUnusablePages(): array
    {
        return [
            'a page the panel does not register' => [fn (): SettingsPageModuleProvider => SettingsPageModuleProvider::owning(app(), 'test-module', UnregisteredClusterSettingsPage::class)],
            'a page outside the settings cluster' => [fn (): SettingsPageModuleProvider => SettingsPageModuleProvider::owning(app(), 'test-module', ModulesPage::class)],
            'a provider that throws' => [fn (): SettingsPageModuleProvider => SettingsPageModuleProvider::throwing(app(), 'test-module')],
        ];
    }

    /**
     * @param  \Closure(): SettingsPageModuleProvider  $provider
     */
    #[DataProvider('modulesWithUnusablePages')]
    public function test_a_module_whose_declared_page_cannot_be_used_is_not_found(\Closure $provider): void
    {
        // Not edited here either: the module chose to leave the generic page
        ModuleModel::factory()->enabled()->create(['name' => 'test-module']);
        $this->registerBootedProvider($provider());
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(ModuleSettingsPage::getUrl(['module' => 'test-module']))
            ->assertNotFound();
    }

    public function test_saving_a_module_with_its_own_settings_page_stores_nothing(): void
    {
        // mount() redirects such a module, so Livewire leaves no component to call save() on (and
        // the locked $module keeps an open page from switching to it): this checks the guard in
        // save() itself, the defence of last resort
        ModuleModel::factory()->enabled()->create(['name' => 'test-module']);
        $this->registerOnAdminPanel(pages: [OwnClusterSettingsPage::class]);
        $this->registerBootedProvider(SettingsPageModuleProvider::owning(app(), 'test-module', OwnClusterSettingsPage::class));
        $this->actingAs(UserModel::factory()->admin()->create());

        $page = app(ModuleSettingsPage::class);
        $page->module = 'test-module';

        $this->assertFalse($page->hasSettings());
        $page->save();

        $this->assertDatabaseMissing('settings', ['key' => 'module_settings:test-module']);
    }

    public function test_the_module_of_an_open_page_cannot_be_swapped_for_another(): void
    {
        // form() and save() follow $module: a client that rewrites it would edit another module
        ModuleModel::factory()->enabled()->create(['name' => 'test-module']);
        ModuleModel::factory()->enabled()->create(['name' => 'own-page-module']);
        $this->loadTestModuleWithSettings();
        $this->registerOnAdminPanel(pages: [OwnClusterSettingsPage::class]);
        $this->registerBootedProvider(SettingsPageModuleProvider::owning(app(), 'own-page-module', OwnClusterSettingsPage::class));
        $this->actingAs(UserModel::factory()->admin()->create());
        $component = Livewire::test(ModuleSettingsPage::class, ['module' => 'test-module']);

        try {
            $component->set('module', 'own-page-module')->set('data.greeting', 'hello')->call('save');
            $this->fail('The module of the page must be locked.');
        } catch (CannotUpdateLockedPropertyException) {
            // Refused before anything runs
        }

        $this->assertDatabaseMissing('settings', ['key' => 'module_settings:own-page-module']);
    }

    private function loadTestModuleWithSettings(): void
    {
        $this->registerBootedProvider(new class(app()) extends ModuleServiceProvider
        {
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
