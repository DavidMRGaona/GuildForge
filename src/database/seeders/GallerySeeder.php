<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use Illuminate\Database\Seeder;

final class GallerySeeder extends Seeder
{
    /**
     * Seed the galleries table.
     * Photos are not seeded because they require real Cloudinary images.
     */
    public function run(): void
    {
        // Published galleries
        GalleryModel::factory()->published()->create([
            'title' => 'Torneo de verano 2024',
            'slug' => 'torneo-verano-2024',
            'description' => 'Fotos del torneo de verano de la asociación.',
        ]);

        GalleryModel::factory()->published()->create([
            'title' => 'Taller de iniciación',
            'slug' => 'taller-iniciacion',
            'description' => 'Fotos de nuestros talleres de iniciación.',
        ]);

        GalleryModel::factory()->published()->create([
            'title' => 'Nuestro local',
            'slug' => 'nuestro-local',
            'description' => 'Fotos de las instalaciones de la asociación.',
        ]);

        // Draft gallery
        GalleryModel::factory()->draft()->create([
            'title' => 'Campeonato regional (pendiente)',
            'slug' => 'campeonato-regional-pendiente',
            'description' => 'Fotos del último campeonato regional. En proceso de selección.',
        ]);

        // Additional random galleries
        GalleryModel::factory()->published()->count(2)->create();
        GalleryModel::factory()->draft()->count(1)->create();
    }
}
