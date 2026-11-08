<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The panel sidebar: entries grouped by task in the core's groups (core#409).
 */
final class PanelNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_legacy_string_group_with_a_core_label_merges_into_it(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        Filament::setCurrentPanel('admin');
        $panel = Filament::getPanel('admin');
        $panel->navigationItems([NavigationItem::make('Legacy item')->group('Contenido')->url('/legacy')->sort(50)]);

        $content = array_values(array_filter(
            $panel->getNavigation(),
            static fn (NavigationGroup $group): bool => $group->getLabel() === 'Contenido',
        ));

        $this->assertCount(1, $content);
        $labels = collect($content[0]->getItems())->map(static fn (NavigationItem $item): string => $item->getLabel())->all();
        $this->assertContains('Legacy item', $labels);
        $this->assertContains('Eventos', $labels);
    }
}
