<?php

declare(strict_types=1);

namespace Tests\Support\Modules;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Route;

/**
 * Puts a module's Filament resources and pages on the admin panel inside a test.
 *
 * The panel is built while the application boots, before a test enables its module,
 * so the module's resources and pages are neither registered nor routed.
 */
trait InteractsWithModulePanel
{
    /**
     * @param  list<class-string<Resource>>  $resources
     * @param  list<class-string<Page>>  $pages
     */
    protected function registerOnAdminPanel(array $resources = [], array $pages = []): void
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        $panel->resources($resources);
        $panel->pages($pages);

        Route::name($panel->generateRouteName(''))
            ->prefix($panel->getPath())
            ->group(function () use ($panel, $resources, $pages): void {
                foreach ($resources as $resource) {
                    $resource::registerRoutes($panel);
                }

                foreach ($pages as $page) {
                    $page::registerRoutes($panel);
                }
            });

        app('router')->getRoutes()->refreshNameLookups();
    }

    protected function actingAsPanelAdmin(): UserModel
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        return $admin;
    }
}
