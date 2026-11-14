<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\ModuleSettingsPage;
use App\Filament\Pages\ModulesPage;
use App\Filament\Pages\Settings\DashboardSettings;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Modules\ModuleServiceProvider;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\Modules\RegistersBootedModuleProviders;
use Tests\Support\Modules\UnregisteredClusterSettingsPage;
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
        // Old links and bookmarks keep working; the page itself checks the module's permission
        ModuleModel::factory()->enabled()->create(['name' => 'test-module']);
        $this->loadTestModuleWithSettingsPage(DashboardSettings::class);
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(ModuleSettingsPage::getUrl(['module' => 'test-module']))
            ->assertRedirect(DashboardSettings::getUrl());
    }

    public function test_a_module_whose_declared_page_cannot_be_used_is_not_found(): void
    {
        // Not edited here either: the module chose to leave the generic page
        ModuleModel::factory()->enabled()->create(['name' => 'test-module']);
        $this->loadTestModuleWithSettingsPage(UnregisteredClusterSettingsPage::class);
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(ModuleSettingsPage::getUrl(['module' => 'test-module']))
            ->assertNotFound();
    }

    public function test_saving_a_module_with_its_own_settings_page_stores_nothing(): void
    {
        // A Livewire call to save() bypasses mount(): the guard in save() must refuse it too
        ModuleModel::factory()->enabled()->create(['name' => 'test-module']);
        $this->loadTestModuleWithSettingsPage(DashboardSettings::class);
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
        $this->registerBootedProvider(new class(app()) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'own-page-module';
            }

            public function getSettingsPage(): string
            {
                return DashboardSettings::class;
            }

            public function getSettingsSchema(): array
            {
                return [TextInput::make('greeting')];
            }
        });
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

    /**
     * @param  class-string<Page>  $page
     */
    private function loadTestModuleWithSettingsPage(string $page): void
    {
        $this->registerBootedProvider(new class(app(), $page) extends ModuleServiceProvider
        {
            /**
             * @param  class-string<Page>  $page
             */
            public function __construct(Application $app, private readonly string $page)
            {
                parent::__construct($app);
            }

            public function moduleName(): string
            {
                return 'test-module';
            }

            public function getSettingsPage(): string
            {
                return $this->page;
            }

            // Still returned, as by a module that has not dropped it yet: the declared page wins
            public function getSettingsSchema(): array
            {
                return [TextInput::make('greeting')];
            }
        });
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
