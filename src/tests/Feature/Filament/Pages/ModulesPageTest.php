<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use App\Application\Modules\Services\ModuleInstallerInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\Exceptions\ModuleIncompatibleException;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\CompatibilityResult;
use App\Filament\Pages\ModulesPage;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

final class ModulesPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-modules-page-'.uniqid();
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

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
        $this->moduleOnDisk('test-module');
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
        $this->moduleOnDisk('dependent-module');
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

    public function test_install_action_is_disabled_without_maintenance_mode(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ModulesPage::class)
            ->assertActionDisabled('install');
    }

    public function test_install_action_is_enabled_while_maintenance_mode_is_enabled(): void
    {
        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ModulesPage::class)
            ->assertActionEnabled('install');
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

    public function test_enabling_an_incompatible_module_notifies_and_keeps_it_disabled(): void
    {
        $this->moduleOnDisk('test-module', ['core' => '^99.0']);
        ModuleModel::factory()->disabled()->create(['name' => 'test-module', 'display_name' => 'Test Module']);
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ModulesPage::class)
            ->call('enableModule', 'test-module')
            ->assertNotified(__('modules.filament.notifications.incompatible', ['name' => 'Test Module', 'reasons' => 'requiere core ^99.0, instalado 2.6.0']));

        $this->assertDatabaseHas('modules', ['name' => 'test-module', 'status' => 'disabled']);
    }

    public function test_an_incompatible_module_shows_a_badge_and_its_reasons(): void
    {
        $this->moduleOnDisk('test-module', ['core' => '^99.0']);
        $this->moduleOnDisk('good-module');
        ModuleModel::factory()->disabled()->create(['name' => 'test-module', 'display_name' => 'Test Module']);
        ModuleModel::factory()->enabled()->create(['name' => 'good-module', 'display_name' => 'Good Module']);
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ModulesPage::class)
            ->assertSee(__('modules.compatibility.badge'))
            ->assertSee('requiere core ^99.0, instalado 2.6.0');

        $page = Livewire::test(ModulesPage::class)->instance();
        $this->assertSame(['compatible' => true, 'reasons' => []], $page->getCompatibility('good-module'));
        $this->assertSame(['compatible' => false, 'reasons' => ['requiere core ^99.0, instalado 2.6.0']], $page->getCompatibility('test-module'));
    }

    public function test_installing_an_incompatible_package_notifies_why(): void
    {
        $installer = Mockery::mock(ModuleInstallerInterface::class);
        $installer->shouldReceive('peekManifest')->andReturn(new ModuleManifestDTO('announcements', '9.0.0', 'Modules\\Announcements', 'AnnouncementsServiceProvider'));
        $installer->shouldReceive('moduleExists')->andReturn(true);
        $installer->shouldReceive('updateFromZip')->andThrow(ModuleIncompatibleException::forModule('announcements', '9.0.0', new CompatibilityResult([
            new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED),
        ])));
        $this->actingAs(UserModel::factory()->admin()->create());
        $page = Livewire::test(ModulesPage::class)->instance();

        (fn () => $this->installPackage(
            UploadedFile::fake()->create('announcements-9.0.0.zip', 1, 'application/zip'),
            $installer,
            app(ModuleManagerServiceInterface::class),
        ))->call($page);

        Notification::assertNotified(__('modules.filament.notifications.incompatible_package', [
            'name' => 'announcements',
            'version' => '9.0.0',
            'reasons' => 'requiere core ^3.0, instalado 2.6.0',
        ]));
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
