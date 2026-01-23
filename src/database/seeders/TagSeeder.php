<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class TagSeeder extends Seeder
{
    /**
     * Seed the tags table with hierarchical tags.
     */
    public function run(): void
    {
        $this->createWargamesTags();
        $this->createRoleplayingTags();
        $this->createBoardGamesTags();
        $this->createEventTypeTags();
    }

    private function createWargamesTags(): void
    {
        $parent = TagModel::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Wargames',
            'slug' => 'wargames',
            'applies_to' => ['events', 'articles', 'galleries'],
            'color' => '#DC2626',
            'description' => 'Juegos de estrategia con miniaturas',
            'sort_order' => 1,
        ]);

        $children = [
            ['name' => 'Warhammer 40K', 'slug' => 'warhammer-40k', 'sort_order' => 1],
            ['name' => 'Age of Sigmar', 'slug' => 'age-of-sigmar', 'sort_order' => 2],
            ['name' => 'Históricos', 'slug' => 'historicos', 'sort_order' => 3],
        ];

        foreach ($children as $child) {
            TagModel::create([
                'id' => Str::uuid()->toString(),
                'name' => $child['name'],
                'slug' => $child['slug'],
                'parent_id' => $parent->id,
                'applies_to' => ['events', 'articles', 'galleries'],
                'color' => '#DC2626',
                'sort_order' => $child['sort_order'],
            ]);
        }
    }

    private function createRoleplayingTags(): void
    {
        $parent = TagModel::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Juegos de rol',
            'slug' => 'juegos-de-rol',
            'applies_to' => ['events', 'articles', 'galleries'],
            'color' => '#7C3AED',
            'description' => 'Juegos de rol de mesa',
            'sort_order' => 2,
        ]);

        $children = [
            ['name' => 'D&D', 'slug' => 'dnd', 'sort_order' => 1],
            ['name' => 'Pathfinder', 'slug' => 'pathfinder', 'sort_order' => 2],
            ['name' => 'Call of Cthulhu', 'slug' => 'call-of-cthulhu', 'sort_order' => 3],
        ];

        foreach ($children as $child) {
            TagModel::create([
                'id' => Str::uuid()->toString(),
                'name' => $child['name'],
                'slug' => $child['slug'],
                'parent_id' => $parent->id,
                'applies_to' => ['events', 'articles', 'galleries'],
                'color' => '#7C3AED',
                'sort_order' => $child['sort_order'],
            ]);
        }
    }

    private function createBoardGamesTags(): void
    {
        TagModel::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Juegos de mesa',
            'slug' => 'juegos-de-mesa',
            'applies_to' => ['events', 'articles', 'galleries'],
            'color' => '#059669',
            'description' => 'Juegos de mesa modernos y clásicos',
            'sort_order' => 3,
        ]);
    }

    private function createEventTypeTags(): void
    {
        $parent = TagModel::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Evento',
            'slug' => 'evento',
            'applies_to' => ['events'],
            'color' => '#6B7280',
            'description' => 'Tipos de evento',
            'sort_order' => 4,
        ]);

        $children = [
            ['name' => 'Torneo', 'slug' => 'torneo', 'sort_order' => 1],
            ['name' => 'Taller', 'slug' => 'taller', 'sort_order' => 2],
            ['name' => 'Sesión Abierta', 'slug' => 'sesion-abierta', 'sort_order' => 3],
        ];

        foreach ($children as $child) {
            TagModel::create([
                'id' => Str::uuid()->toString(),
                'name' => $child['name'],
                'slug' => $child['slug'],
                'parent_id' => $parent->id,
                'applies_to' => ['events'],
                'color' => '#6B7280',
                'sort_order' => $child['sort_order'],
            ]);
        }
    }
}
