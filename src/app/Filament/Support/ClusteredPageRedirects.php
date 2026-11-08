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
     * Path and name are built as Filament builds a page outside a cluster; the closures capture
     * only strings, so the routes can be cached.
     */
    public static function register(Panel $panel): void
    {
        $panelId = $panel->getId();

        foreach (self::clusteredPages($panel) as $page) {
            Route::get($page::getRoutePath($panel), static fn (Request $request): RedirectResponse => redirect()->to($page::getUrl($request->query(), panel: $panelId)))
                ->name(self::oldRelativeRouteName($page, $panel));
        }
    }

    /**
     * The route name a clustered page answers to now, given the one it had before joining its
     * cluster; null when the name is not such an old name.
     */
    public static function currentRouteName(Panel $panel, string $routeName): ?string
    {
        foreach (self::clusteredPages($panel) as $page) {
            if ($panel->generateRouteName(self::oldRelativeRouteName($page, $panel)) === $routeName) {
                return $page::getRouteName($panel);
            }
        }

        return null;
    }

    /**
     * @return list<class-string<Page>>
     */
    private static function clusteredPages(Panel $panel): array
    {
        /** @var list<class-string<Page>> $pages */
        $pages = array_values($panel->getPages());

        return array_values(array_filter($pages, static fn (string $page): bool => filled($page::getCluster())));
    }

    /**
     * @param  class-string<Page>  $page
     */
    private static function oldRelativeRouteName(string $page, Panel $panel): string
    {
        return 'pages.'.$page::getRelativeRouteName($panel);
    }
}
