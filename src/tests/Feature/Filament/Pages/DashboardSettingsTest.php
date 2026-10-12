<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Services\DashboardWidgetConfigServiceInterface;
use App\Filament\Pages\Settings\DashboardSettings;
use App\Filament\Widgets\UpcomingEventsWidget;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class DashboardSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** A widget of a module that is disabled or rejected right now: not on the panel */
    private const string ABSENT_WIDGET = 'Modules\\GameTables\\Filament\\Widgets\\PendingModerationWidget';

    public function test_saving_keeps_the_settings_of_widgets_the_panel_does_not_offer_now(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        app(DashboardWidgetConfigServiceInterface::class)->saveConfig([
            self::ABSENT_WIDGET => ['enabled' => false, 'sort' => 7, 'limit' => 3],
        ]);

        Livewire::test(DashboardSettings::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $config = app(DashboardWidgetConfigServiceInterface::class)->getConfig();
        $this->assertSame(['enabled' => false, 'sort' => 7, 'limit' => 3], $config[self::ABSENT_WIDGET] ?? null);
        $this->assertArrayHasKey(UpcomingEventsWidget::class, $config);
    }
}
