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
                'sort_order' => 1,
            ],
            [
                'label' => 'Eventos',
                'route' => 'events.index',
                'icon' => 'heroicon-o-calendar-days',
                'sort_order' => 2,
            ],
            [
                'label' => 'Calendario',
                'route' => 'calendar',
                'icon' => 'heroicon-o-calendar',
                'sort_order' => 3,
            ],
            [
                'label' => 'Artículos',
                'route' => 'articles.index',
                'icon' => 'heroicon-o-newspaper',
                'sort_order' => 4,
            ],
            [
                'label' => 'Galería',
                'route' => 'galleries.index',
                'icon' => 'heroicon-o-photo',
                'sort_order' => 5,
            ],
            [
                'label' => 'Nosotros',
                'route' => 'about',
                'icon' => 'heroicon-o-user-group',
                'sort_order' => 6,
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
                'sort_order' => 1,
            ],
            [
                'label' => 'Artículos',
                'route' => 'articles.index',
                'sort_order' => 2,
            ],
            [
                'label' => 'Galería',
                'route' => 'galleries.index',
                'sort_order' => 3,
            ],
            [
                'label' => 'Nosotros',
                'route' => 'about',
                'sort_order' => 4,
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
                    'is_active' => true,
                ],
            );
        }
    }
}
