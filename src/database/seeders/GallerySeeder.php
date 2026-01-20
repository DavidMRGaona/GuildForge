<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Illuminate\Database\Seeder;

final class GallerySeeder extends Seeder
{
    /**
     * Seed the galleries table.
     * Photos are not seeded because they require real Cloudinary images.
     */
    public function run(): void
    {
        // Get tags for attaching
        $warhammer40kTag = TagModel::where('slug', 'warhammer-40k')->first();
        $wargamesTag = TagModel::where('slug', 'wargames')->first();
        $juegosRolTag = TagModel::where('slug', 'juegos-de-rol')->first();
        $juegosMesaTag = TagModel::where('slug', 'juegos-de-mesa')->first();

        // Published galleries
        $torneoVerano = GalleryModel::factory()->published()->create([
            'title' => 'Torneo de verano 2024',
            'slug' => 'torneo-verano-2024',
            'description' => 'Fotos del torneo de verano de la asociación.',
        ]);
        $torneoVerano->tags()->attach(array_filter([$warhammer40kTag?->id, $wargamesTag?->id]));

        $tallerIniciacion = GalleryModel::factory()->published()->create([
            'title' => 'Taller de iniciación',
            'slug' => 'taller-iniciacion',
            'description' => 'Fotos de nuestros talleres de iniciación.',
        ]);
        $tallerIniciacion->tags()->attach(array_filter([$juegosRolTag?->id]));

        $nuestroLocal = GalleryModel::factory()->published()->create([
            'title' => 'Nuestro local',
            'slug' => 'nuestro-local',
            'description' => 'Fotos de las instalaciones de la asociación.',
        ]);
        $nuestroLocal->tags()->attach(array_filter([$wargamesTag?->id, $juegosRolTag?->id, $juegosMesaTag?->id]));

        // Draft gallery
        $campeonatoPendiente = GalleryModel::factory()->draft()->create([
            'title' => 'Campeonato regional (pendiente)',
            'slug' => 'campeonato-regional-pendiente',
            'description' => 'Fotos del último campeonato regional. En proceso de selección.',
        ]);
        $campeonatoPendiente->tags()->attach(array_filter([$warhammer40kTag?->id]));

        // Additional random galleries
        GalleryModel::factory()->published()->count(2)->create();
        GalleryModel::factory()->draft()->count(1)->create();
    }
}
