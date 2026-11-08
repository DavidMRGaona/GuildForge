<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * A page that joins a cluster gets the cluster slug in its path and route name. Bookmarks, help
 * guide buttons stored in the database and module guides still use the old ones, so each
 * clustered page keeps a redirect under its old path and route name, query string included.
 */
final class ClusteredPageRedirects
{
    /**
     * Called inside the panel's authenticated route group (names prefixed `filament.<panel>.`).
     */
    public static function register(Panel $panel): void
    {
        /** @var class-string<Page> $page */
        foreach ($panel->getPages() as $page) {
            if (blank($page::getCluster())) {
                continue;
            }

            $slug = $page::getSlug($panel);

            Route::get($slug, static fn (Request $request): RedirectResponse => redirect()->to($page::getUrl($request->query(), panel: $panel->getId())))
                ->name("pages.{$slug}");
        }
    }
}
