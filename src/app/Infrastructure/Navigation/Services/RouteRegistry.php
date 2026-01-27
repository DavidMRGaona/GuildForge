<?php

declare(strict_types=1);

namespace App\Infrastructure\Navigation\Services;

use App\Application\Modules\Services\ModuleRouteRegistryInterface;
use App\Application\Navigation\Services\RouteRegistryInterface;

/**
 * Provides a curated list of routes available for menu item configuration.
 */
final readonly class RouteRegistry implements RouteRegistryInterface
{
    public function __construct(
        private ModuleRouteRegistryInterface $moduleRouteRegistry,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getAvailableRoutes(): array
    {
        $coreRoutes = $this->getCoreRoutes();
        $moduleRoutes = $this->moduleRouteRegistry->toRouteOptions();

        return array_merge($moduleRoutes, $coreRoutes);
    }

    /**
     * @return array<string, string>
     */
    private function getCoreRoutes(): array
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

            // Páginas legales
            'legal.show:aviso-legal' => __('navigation.routes.legal_notice'),
            'legal.show:politica-de-privacidad' => __('navigation.routes.privacy_policy'),
            'legal.show:politica-de-cookies' => __('navigation.routes.cookie_policy'),
            'legal.show:terminos-y-condiciones' => __('navigation.routes.terms_and_conditions'),

            // Auth
            'login' => __('navigation.routes.login'),
            'register' => __('navigation.routes.register'),
            'profile.edit' => __('navigation.routes.profile'),
        ];
    }
}
