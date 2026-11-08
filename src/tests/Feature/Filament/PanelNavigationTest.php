<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\KnowledgeBasePage;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * The panel sidebar: entries grouped by task in the core's groups (core#409).
 */
final class PanelNavigationTest extends TestCase
{
    use CreatesUsersWithPermissions;
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

    public function test_a_group_nobody_registered_goes_after_the_core_groups(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        Filament::setCurrentPanel('admin');
        $panel = Filament::getPanel('admin');
        $panel->navigationItems([NavigationItem::make('Unregistered item')->group('Unregistered group')->url('/unregistered')]);

        $labels = array_values(array_filter(array_map(
            static fn (NavigationGroup $group): ?string => $group->getLabel(),
            $panel->getNavigation(),
        )));

        $this->assertSame('Unregistered group', end($labels));
        $this->assertSame('Contenido', $labels[0]);
    }

    public function test_admin_sees_the_core_entries_grouped_by_task(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        Filament::setCurrentPanel('admin');

        $tree = [];
        foreach (Filament::getPanel('admin')->getNavigation() as $group) {
            $tree[$group->getLabel() ?? ''] = collect($group->getItems())
                ->map(static fn (NavigationItem $item): string => $item->getLabel())
                ->values()
                ->all();
        }

        $this->assertSame([
            '' => ['Escritorio'],
            'Contenido' => ['Eventos', 'Artículos', 'Galerías', 'Guías de ayuda'],
            'Personas' => ['Usuarios', 'Roles'],
            'Sitio web' => ['Portada', 'Navegación', 'Nosotros', 'Páginas legales'],
            'Catálogos' => ['Etiquetas'],
            'Sistema' => ['Configuración', 'Módulos', 'Actualizaciones de módulos', 'Actualización del core'],
        ], $tree);
    }

    public function test_an_editor_sees_only_groups_with_entries_they_may_open(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.view_any', 'hero_slides.view_any']));
        Filament::setCurrentPanel('admin');

        $labels = array_values(array_filter(array_map(
            static fn (NavigationGroup $group): ?string => $group->getLabel(),
            Filament::getPanel('admin')->getNavigation(),
        )));

        $this->assertSame(['Contenido', 'Sitio web'], $labels);
    }

    public function test_help_is_not_in_the_sidebar(): void
    {
        $this->assertFalse(KnowledgeBasePage::shouldRegisterNavigation());
    }
}
