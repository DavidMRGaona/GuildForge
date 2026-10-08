<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\ModuleUpdatesPage;
use App\Filament\Resources\TagResource;
use App\Filament\Resources\TagResource\Pages\EditTag;
use App\Filament\Resources\TagResource\Pages\ListTags;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\TestCase;

/**
 * Module suites use this helper to drive their panel pages; here it runs against a host
 * resource and page whose routes are dropped first, which is where a module stands when
 * its test enables it after the panel was built.
 */
final class InteractsWithModulePanelTest extends TestCase
{
    use InteractsWithModulePanel;
    use LazilyRefreshDatabase;

    private const string RESOURCE_ROUTES = 'filament.admin.resources.tags.';

    private const string PAGE_ROUTE = 'filament.admin.pages.module-updates-page';

    public function test_it_routes_the_resources_and_pages_it_puts_on_the_panel(): void
    {
        $tagsUrl = TagResource::getUrl('index');
        $pageUrl = ModuleUpdatesPage::getUrl();
        $this->forgetPanelRoutes();
        $this->assertFalse(Route::has(self::RESOURCE_ROUTES.'index'));
        $this->assertFalse(Route::has(self::PAGE_ROUTE));

        $this->registerOnAdminPanel([TagResource::class], [ModuleUpdatesPage::class]);

        $this->assertSame('admin', Filament::getCurrentPanel()?->getId());
        $this->assertContains(TagResource::class, Filament::getPanel('admin')->getResources());
        $this->assertSame($tagsUrl, TagResource::getUrl('index'));
        $this->assertTrue(Route::has(self::RESOURCE_ROUTES.'create'));
        $this->assertTrue(Route::has(self::RESOURCE_ROUTES.'edit'));
        $this->assertSame($pageUrl, ModuleUpdatesPage::getUrl());
    }

    public function test_a_panel_admin_drives_the_resource_and_page_with_livewire(): void
    {
        $this->forgetPanelRoutes();
        $this->registerOnAdminPanel([TagResource::class], [ModuleUpdatesPage::class]);

        $admin = $this->actingAsPanelAdmin();

        $this->assertTrue($admin->is(auth()->user()));
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));

        // Each row links to its edit page, so listing a record needs the resource's routes
        $tag = TagModel::factory()->create();
        Livewire::test(ListTags::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$tag]);
        Livewire::test(EditTag::class, ['record' => $tag->getRouteKey()])
            ->assertOk()
            ->assertFormSet(['name' => $tag->name]);
        Livewire::test(ModuleUpdatesPage::class)->assertOk();
    }

    private function forgetPanelRoutes(): void
    {
        $routes = new RouteCollection();

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, self::RESOURCE_ROUTES) && $name !== self::PAGE_ROUTE) {
                $routes->add($route);
            }
        }

        app('router')->setRoutes($routes);
    }
}
