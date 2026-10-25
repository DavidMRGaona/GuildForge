<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
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
        $query = Request::create(SiteSettings::getMaintenanceTabUrl())->query->all();

        $page = Livewire::test(SiteSettings::class)->instance();
        $this->assertInstanceOf(SiteSettings::class, $page);
        request()->query->replace($query);

        $tabs = $page->form->getComponents()[0];
        $this->assertInstanceOf(Tabs::class, $tabs);
        $activeTab = $tabs->getChildComponents()[$tabs->getActiveTab() - 1] ?? null;
        $this->assertInstanceOf(Tab::class, $activeTab);

        $this->assertSame(__('filament.settings.tabs.maintenance'), $activeTab->getLabel());
    }
}
