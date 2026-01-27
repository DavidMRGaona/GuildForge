<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Services;

use App\Application\Navigation\Services\RouteRegistryInterface;

/**
 * Provides a curated list of routes available for menu item configuration.
 */
final readonly class RouteRegistry implements RouteRegistryInterface
{
    /**
     * @inheritDoc
     */
    public function getAvailableRoutes(): array
    {
        return [
            // Páginas principales
            'home' => __('navigation.routes.home'),
            'about' => __('navigation.routes.about'),
            'calendar' => __('navigation.routes.calendar'),
            'contact' => __('navigation.routes.contact'),

            // Eventos
            'events.index' => __('navigation.routes.events'),
            'events.show' => __('navigation.routes.event_detail'),

            // Artículos
            'articles.index' => __('navigation.routes.articles'),
            'articles.show' => __('navigation.routes.article_detail'),

            // Galería
            'galleries.index' => __('navigation.routes.galleries'),
            'galleries.show' => __('navigation.routes.gallery_detail'),

            // Auth
            'login' => __('navigation.routes.login'),
            'register' => __('navigation.routes.register'),
            'profile.edit' => __('navigation.routes.profile'),
        ];
    }
}
