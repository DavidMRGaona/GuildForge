<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Filament\Pages\ModulesPage;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class IncompatibleModulesBannerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir().'/gf-banner-'.uniqid();
        File::ensureDirectoryExists($this->modulesPath);
        config(['modules.path' => $this->modulesPath]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->modulesPath);
        parent::tearDown();
    }

    public function test_panel_names_each_rejected_module_and_why(): void
    {
        $this->enabledModule('announcements', 'Anuncios', ['core' => '^99.0']);
        $this->actingAs(UserModel::factory()->admin()->create());
        $core = $this->app->make(CoreVersionServiceInterface::class)->getCurrentVersion()->value();

        $this->get('/admin')
            ->assertOk()
            ->assertSee(trans_choice('modules.compatibility.banner', 1))
            ->assertSee('Anuncios')
            ->assertSee("requiere core ^99.0, instalado {$core}")
            ->assertSee('bg-custom-600', false)
            ->assertSee('--color-600: var(--danger-600)', false);
    }

    public function test_banner_names_an_enabled_module_whose_directory_disappeared(): void
    {
        ModuleModel::factory()->enabled()->create(['name' => 'vanished', 'display_name' => 'Desaparecido']);
        app(EnabledModulesResolverInterface::class)->reset();
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Desaparecido')
            ->assertSee('no se encuentra module.json');
    }

    public function test_banner_links_to_the_modules_page_only_for_those_who_can_open_it(): void
    {
        $this->enabledModule('announcements', 'Anuncios', ['core' => '^99.0']);

        $this->actingAs(UserModel::factory()->admin()->create());
        $this->get('/admin')
            ->assertSee(__('modules.compatibility.banner_link'))
            ->assertSee('href="'.ModulesPage::getUrl().'"', false);

        $this->actingAs(UserModel::factory()->editor()->create());
        $this->get('/admin')
            ->assertSee(trans_choice('modules.compatibility.banner', 1))
            ->assertDontSee(__('modules.compatibility.banner_link'));
    }

    public function test_guests_on_the_login_page_do_not_see_it(): void
    {
        $this->enabledModule('announcements', 'Anuncios', ['core' => '^99.0']);

        $this->get('/admin/login')
            ->assertOk()
            ->assertDontSee(trans_choice('modules.compatibility.banner', 1))
            ->assertDontSee('Anuncios');
    }

    public function test_no_banner_while_every_enabled_module_is_compatible(): void
    {
        $this->enabledModule('announcements', 'Anuncios', ['core' => '>=2.0']);
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get('/admin')
            ->assertOk()
            ->assertDontSee(trans_choice('modules.compatibility.banner', 1));
    }

    /**
     * @param  array<string, mixed>  $requires
     */
    private function enabledModule(string $name, string $displayName, array $requires): void
    {
        File::ensureDirectoryExists("{$this->modulesPath}/{$name}");
        File::put("{$this->modulesPath}/{$name}/module.json", (string) json_encode([
            'name' => $name,
            'displayName' => $displayName,
            'version' => '1.0.0',
            'namespace' => 'Modules\\'.ucfirst($name),
            'provider' => ucfirst($name).'ServiceProvider',
            'requires' => $requires,
        ]));
        ModuleModel::factory()->enabled()->create(['name' => $name, 'display_name' => $displayName]);

        // The panel resolved its modules when the test application booted, before this row existed
        app(EnabledModulesResolverInterface::class)->reset();
    }
}
