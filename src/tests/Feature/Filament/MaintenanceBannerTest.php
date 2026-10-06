<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class MaintenanceBannerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_panel_shows_a_banner_while_maintenance_is_enabled(): void
    {
        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get('/admin')
            ->assertOk()
            ->assertSee(__('filament.maintenance.banner'));
    }

    public function test_banner_links_to_the_maintenance_tab_of_the_site_settings(): void
    {
        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get('/admin')
            ->assertSee('href="'.SiteSettings::getUrl(['tab' => 'settings-maintenance-tab']).'"', false);
    }

    public function test_banner_uses_background_utilities_present_in_the_compiled_panel_css(): void
    {
        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get('/admin')
            ->assertSee('bg-custom-600', false)
            ->assertSee('var(--warning-600)', false);
    }

    public function test_panel_shows_no_banner_while_maintenance_is_disabled(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get('/admin')
            ->assertOk()
            ->assertDontSee(__('filament.maintenance.banner'));
    }
}
