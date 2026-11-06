<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Application\Navigation\Services\RouteRegistryInterface;
use App\Infrastructure\Knowledge\Rendering\ActionLinkResolver;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Pages a guide's button can open: the website pages offered for the menu and the panel's pages and
 * lists. Only named routes without required parameters, which is what a guide button can resolve.
 */
final class KnowledgeActionTargets
{
    /**
     * @return array<string, array<string, string>> group label => [target => label]
     */
    public static function grouped(): array
    {
        return array_filter([
            (string) __('filament.knowledge_guides.blocks.action.site_pages') => self::sitePages(),
            (string) __('filament.knowledge_guides.blocks.action.panel_pages') => self::panelPages(),
        ]);
    }

    public static function label(string $target): ?string
    {
        foreach (self::grouped() as $options) {
            if (isset($options[$target])) {
                return $options[$target];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function sitePages(): array
    {
        $pages = [];
        foreach (resolve(RouteRegistryInterface::class)->getAvailableRoutes() as $target => $label) {
            // "route:slug" entries carry a required parameter, which a guide button cannot pass
            if (! str_contains($target, ':') && self::resolvable($target)) {
                $pages[$target] = $label;
            }
        }
        asort($pages, SORT_NATURAL | SORT_FLAG_CASE);

        return $pages;
    }

    /**
     * @return array<string, string>
     */
    private static function panelPages(): array
    {
        $pages = [];

        try {
            $panel = Filament::getPanel('admin');

            /** @var class-string<Page> $page */
            foreach ($panel->getPages() as $page) {
                $route = $page::getRouteName($panel);
                if (self::resolvable($route)) {
                    $pages[$route] = (string) $page::getNavigationLabel();
                }
            }

            /** @var class-string<resource> $resource */
            foreach ($panel->getResources() as $resource) {
                $route = $resource::getRouteBaseName($panel).'.index';
                if (self::resolvable($route)) {
                    $pages[$route] = (string) $resource::getNavigationLabel();
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        asort($pages, SORT_NATURAL | SORT_FLAG_CASE);

        return $pages;
    }

    private static function resolvable(string $routeName): bool
    {
        $route = Route::getRoutes()->getByName($routeName);

        return $route !== null && ! ActionLinkResolver::hasRequiredParameters($route);
    }
}
