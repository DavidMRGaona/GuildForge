<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Clusters\SettingsCluster;
use App\Filament\Pages\Settings\DashboardSettings;
use App\Filament\Pages\Settings\MailSettings;
use App\Filament\Pages\Settings\MailStatisticsPage;
use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
