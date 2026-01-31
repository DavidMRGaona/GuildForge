<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Navigation\Enums\LinkTarget;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Enums\MenuVisibility;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use Illuminate\Database\Seeder;

final class MenuItemSeeder extends Seeder
{
    /**
     * Seed the menu_items table with core navigation items.
     */
    public function run(): void
    {
        $headerItems = [
            [
                'label' => 'Inicio',
                'route' => 'home',
                'icon' => 'heroicon-o-home',
                'sort_order' => 10,
            ],
            [
                'label' => 'Eventos',
                'route' => 'events.index',
                'icon' => 'heroicon-o-calendar-days',
                'sort_order' => 20,
            ],
            [
                'label' => 'Calendario',
                'route' => 'calendar',
                'icon' => 'heroicon-o-calendar',
                'sort_order' => 30,
            ],
            [
                'label' => 'Artículos',
                'route' => 'articles.index',
                'icon' => 'heroicon-o-newspaper',
                'sort_order' => 40,
            ],
            [
                'label' => 'Galería',
                'route' => 'galleries.index',
                'icon' => 'heroicon-o-photo',
                'sort_order' => 50,
            ],
            [
                'label' => 'Nosotros',
                'route' => 'about',
                'icon' => 'heroicon-o-user-group',
                'sort_order' => 60,
            ],
        ];

        foreach ($headerItems as $item) {
            MenuItemModel::firstOrCreate(
                [
                    'location' => MenuLocation::Header,
                    'route' => $item['route'],
                    'module' => 'core',
                ],
                [
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'sort_order' => $item['sort_order'],
                    'target' => LinkTarget::Self,
                    'visibility' => MenuVisibility::Public,
                    'is_active' => true,
                ],
            );
        }

        $footerItems = [
            [
                'label' => 'Eventos',
                'route' => 'events.index',
                'sort_order' => 51,
            ],
            [
                'label' => 'Artículos',
                'route' => 'articles.index',
                'sort_order' => 52,
            ],
            [
                'label' => 'Galería',
                'route' => 'galleries.index',
                'sort_order' => 53,
            ],
            [
                'label' => 'Nosotros',
                'route' => 'about',
                'sort_order' => 54,
            ],
        ];

        foreach ($footerItems as $item) {
            MenuItemModel::firstOrCreate(
                [
                    'location' => MenuLocation::Footer,
                    'route' => $item['route'],
                    'module' => 'core',
                ],
                [
                    'label' => $item['label'],
                    'icon' => null,
                    'sort_order' => $item['sort_order'],
                    'target' => LinkTarget::Self,
                    'visibility' => MenuVisibility::Public,
                    'is_active' => false,
                ],
            );
        }

        $legalFooterItems = [
            [
                'label' => 'Aviso legal',
                'route' => 'legal.show',
                'route_params' => ['slug' => 'aviso-legal'],
                'sort_order' => 60,
            ],
            [
                'label' => 'Política de privacidad',
                'route' => 'legal.show',
                'route_params' => ['slug' => 'politica-de-privacidad'],
                'sort_order' => 61,
            ],
            [
                'label' => 'Política de cookies',
                'route' => 'legal.show',
                'route_params' => ['slug' => 'politica-de-cookies'],
                'sort_order' => 62,
            ],
            [
                'label' => 'Términos y condiciones',
                'route' => 'legal.show',
                'route_params' => ['slug' => 'terminos-y-condiciones'],
                'sort_order' => 63,
            ],
        ];

        foreach ($legalFooterItems as $item) {
            MenuItemModel::firstOrCreate(
                [
                    'location' => MenuLocation::Footer,
                    'label' => $item['label'],
                    'module' => 'core',
                ],
                [
                    'route' => $item['route'],
                    'route_params' => $item['route_params'],
                    'icon' => null,
                    'sort_order' => $item['sort_order'],
                    'target' => LinkTarget::Self,
                    'visibility' => MenuVisibility::Public,
                    'is_active' => true,
                ],
            );
        }
    }
}
