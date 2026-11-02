<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Application\Services\SettingsServiceInterface;
use App\Filament\Pages\KnowledgeBasePage;
use App\Filament\Pages\SiteSettings;
use App\Filament\Support\PanelSidebarMode;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The panel shares the public site's warm greys, and its sidebar collapses the way the
 * site settings say: not at all, down to icons or out of sight.
 */
final class PanelAppearanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_panel_greys_are_the_public_site_backgrounds(): void
    {
        $gray = Filament::getPanel('admin')->getColors()['gray'] ?? null;

        $this->assertIsArray($gray);
        // Page, surface and elevated backgrounds of the public site in dark mode
        $this->assertSame('#252220', $gray[950]);
        $this->assertSame('#302d2a', $gray[900]);
        $this->assertSame('#3d3936', $gray[800]);
        // Page background in light mode
        $this->assertSame('#fafaf9', $gray[50]);
    }

    /**
     * @return iterable<string, array{0: string|null, 1: bool, 2: bool}>
     */
    public static function sidebarModes(): iterable
    {
        yield 'fixed' => ['fixed', false, false];
        yield 'icons' => ['icons', true, false];
        yield 'hidden' => ['hidden', false, true];
        yield 'not set' => [null, true, false];
        yield 'unknown value' => ['sideways', true, false];
    }

    #[DataProvider('sidebarModes')]
    public function test_sidebar_collapses_as_the_setting_says(?string $mode, bool $toIcons, bool $fully): void
    {
        if ($mode !== null) {
            app(SettingsServiceInterface::class)->set(PanelSidebarMode::SETTING_KEY, $mode);
        }

        $panel = Filament::getPanel('admin');

        $this->assertSame($toIcons, $panel->isSidebarCollapsibleOnDesktop());
        $this->assertSame($fully, $panel->isSidebarFullyCollapsibleOnDesktop());
    }

    public function test_admin_chooses_the_sidebar_mode_in_site_settings(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->assertFormFieldIsVisible(PanelSidebarMode::SETTING_KEY)
            ->fillForm([PanelSidebarMode::SETTING_KEY => 'hidden'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('hidden', app(SettingsServiceInterface::class)->get(PanelSidebarMode::SETTING_KEY));
    }

    public function test_site_settings_reject_an_unknown_sidebar_mode(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(SiteSettings::class)
            ->fillForm([PanelSidebarMode::SETTING_KEY => 'sideways'])
            ->call('save')
            ->assertHasFormErrors([PanelSidebarMode::SETTING_KEY]);
    }

    public function test_help_page_folds_a_collapsible_sidebar(): void
    {
        app(SettingsServiceInterface::class)->set(PanelSidebarMode::SETTING_KEY, 'icons');
        $this->actingAs(UserModel::factory()->editor()->create());

        Livewire::test(KnowledgeBasePage::class)->assertSeeHtml('data-folds-panel-sidebar');
    }

    public function test_help_page_leaves_a_fixed_sidebar_alone(): void
    {
        app(SettingsServiceInterface::class)->set(PanelSidebarMode::SETTING_KEY, 'fixed');
        $this->actingAs(UserModel::factory()->editor()->create());

        Livewire::test(KnowledgeBasePage::class)->assertDontSeeHtml('data-folds-panel-sidebar');
    }
}
