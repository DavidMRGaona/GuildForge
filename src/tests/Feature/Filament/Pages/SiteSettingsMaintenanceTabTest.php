<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\Tabs;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The maintenance banner and the module updates page link to the maintenance tab:
 * the link must open that tab, whatever the query string format of the Filament version.
 */
final class SiteSettingsMaintenanceTabTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_maintenance_tab_url_opens_the_maintenance_tab(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        parse_str((string) parse_url(SiteSettings::getMaintenanceTabUrl(), PHP_URL_QUERY), $query);

        $page = Livewire::test(SiteSettings::class)->instance();
        request()->query->replace($query);

        /** @var Tabs $tabs */
        $tabs = $page->form->getComponents()[0];
        $labels = array_map(static fn ($tab): string => (string) $tab->getLabel(), $tabs->getChildComponents());

        $this->assertSame(__('filament.settings.tabs.maintenance'), $labels[$tabs->getActiveTab() - 1] ?? null);
    }
}
