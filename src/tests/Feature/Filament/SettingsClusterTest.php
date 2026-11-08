<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Clusters\SettingsCluster;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Settings\DashboardSettings;
use App\Filament\Pages\Settings\MailSettings;
use App\Filament\Pages\Settings\MailStatisticsPage;
use App\Filament\Pages\SiteSettings;
use App\Filament\Support\ClusteredPageRedirects;
use App\Filament\Support\KnowledgeActionTargets;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use ReflectionFunction;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * Every settings page lives in one «Configuración» entry of the sidebar (core#409), and the
 * paths and route names they had before the cluster still lead to them.
 */
final class SettingsClusterTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    public function test_settings_pages_live_in_the_cluster(): void
    {
        foreach ([SiteSettings::class, MailSettings::class, MailStatisticsPage::class, DashboardSettings::class] as $page) {
            $this->assertSame(SettingsCluster::class, $page::getCluster(), $page);
        }

        $this->assertStringStartsWith('/admin/configuracion/', (string) parse_url(SiteSettings::getUrl(), PHP_URL_PATH));
    }

    public function test_cluster_sits_in_system_and_lists_its_pages_in_order(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->assertSame('Sistema', SettingsCluster::getNavigationGroup());
        $this->assertSame('Configuración', SettingsCluster::getNavigationLabel());
        $this->get(MailSettings::getUrl())
            ->assertOk()
            ->assertSeeInOrder(['Configuración del sitio', 'Correo electrónico', 'Estadísticas de correo', 'Escritorio']);
    }

    public function test_old_settings_path_redirects_with_its_query(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get('/admin/site-settings?tab=mail')->assertRedirect(SiteSettings::getUrl(['tab' => 'mail']));
        $this->assertSame(url('/admin/site-settings'), route('filament.admin.pages.site-settings'));
    }

    public function test_every_old_settings_route_name_still_resolves(): void
    {
        foreach (['site-settings', 'mail-settings', 'mail-statistics-page', 'dashboard-settings'] as $slug) {
            $this->assertSame(url("/admin/{$slug}"), route("filament.admin.pages.{$slug}"));
        }
    }

    public function test_redirect_routes_capture_no_objects_so_routes_can_be_cached(): void
    {
        $action = Route::getRoutes()->getByName('filament.admin.pages.site-settings')?->getAction('uses');
        $this->assertInstanceOf(Closure::class, $action);

        $reflection = new ReflectionFunction($action);
        $captured = [...$reflection->getStaticVariables(), ...$reflection->getClosureUsedVariables()];

        $this->assertNotSame([], $captured);
        foreach ($captured as $name => $value) {
            $this->assertIsString($value, "\${$name} is captured by the redirect closure");
        }
    }

    public function test_redirects_follow_filaments_route_path_and_name_for_nested_slugs(): void
    {
        $panel = Panel::make()->id('nested-test')->pages([NestedSlugSettingsPage::class]);

        Route::name('filament.nested-test.')->prefix('nested-test')->group(
            static fn () => ClusteredPageRedirects::register($panel),
        );
        Route::getRoutes()->refreshNameLookups();

        $this->assertSame(url('/nested-test/club/fees'), route('filament.nested-test.pages.club.fees'));
    }

    public function test_a_guide_button_saved_with_an_old_route_name_shows_its_page(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        $this->assertSame(SiteSettings::getRouteName(), KnowledgeActionTargets::canonical('filament.admin.pages.site-settings'));
        $this->assertSame('Configuración del sitio', KnowledgeActionTargets::label('filament.admin.pages.site-settings'));
        $this->assertSame('filament.admin.resources.events.index', KnowledgeActionTargets::canonical('filament.admin.resources.events.index'));
    }

    public function test_a_page_of_a_collapsed_group_opens_that_group(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(MailSettings::getUrl())
            ->assertOk()
            ->assertSee('data-expand-active-navigation-group', false)
            ->assertSee("const label = 'Sistema'", false);
    }

    public function test_an_ungrouped_page_opens_no_group(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertDontSee('data-expand-active-navigation-group', false);
    }

    public function test_old_settings_path_asks_guests_to_sign_in(): void
    {
        $this->get('/admin/mail-settings')->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
    }

    public function test_cluster_root_opens_the_first_page_the_user_may_see(): void
    {
        $this->actingAs($this->editorWithPermissions(['mail.view_stats']));

        $this->get(SettingsCluster::getUrl())->assertRedirect(MailStatisticsPage::getUrl());
    }

    public function test_cluster_is_hidden_and_forbidden_without_any_settings_permission(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.view_any']));

        $this->assertFalse(SettingsCluster::shouldRegisterNavigation());
        $this->get(SettingsCluster::getUrl())->assertForbidden();
    }
}

/**
 * A clustered page whose slug has a slash, as a module settings page could have.
 */
final class NestedSlugSettingsPage extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $slug = 'club/fees';
}
