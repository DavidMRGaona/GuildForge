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
        $torneoVerano = $this->firstOrCreateGallery(
            'torneo-verano-2024',
            [
                'title' => 'Torneo de verano 2024',
                'description' => 'Fotos del torneo de verano de la asociación.',
            ],
            fn () => GalleryModel::factory()->published()->raw(),
        );
        $torneoVerano->tags()->syncWithoutDetaching(array_filter([$warhammer40kTag?->id, $wargamesTag?->id]));

        $tallerIniciacion = $this->firstOrCreateGallery(
            'taller-iniciacion',
            [
                'title' => 'Taller de iniciación',
                'description' => 'Fotos de nuestros talleres de iniciación.',
            ],
            fn () => GalleryModel::factory()->published()->raw(),
        );
        $tallerIniciacion->tags()->syncWithoutDetaching(array_filter([$juegosRolTag?->id]));

        $nuestroLocal = $this->firstOrCreateGallery(
            'nuestro-local',
            [
                'title' => 'Nuestro local',
                'description' => 'Fotos de las instalaciones de la asociación.',
            ],
            fn () => GalleryModel::factory()->published()->raw(),
        );
        $nuestroLocal->tags()->syncWithoutDetaching(array_filter([$wargamesTag?->id, $juegosRolTag?->id, $juegosMesaTag?->id]));

        // Draft gallery
        $campeonatoPendiente = $this->firstOrCreateGallery(
            'campeonato-regional-pendiente',
            [
                'title' => 'Campeonato regional (pendiente)',
                'description' => 'Fotos del último campeonato regional. En proceso de selección.',
            ],
            fn () => GalleryModel::factory()->draft()->raw(),
        );
        $campeonatoPendiente->tags()->syncWithoutDetaching(array_filter([$warhammer40kTag?->id]));

        // Additional random galleries (only if we have few galleries)
        $existingCount = GalleryModel::count();
        if ($existingCount < 7) {
            GalleryModel::factory()->published()->count(2)->create();
            GalleryModel::factory()->draft()->count(1)->create();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  callable(): array<string, mixed>  $factoryDefaults
     */
    private function firstOrCreateGallery(string $slug, array $attributes, callable $factoryDefaults): GalleryModel
    {
        return GalleryModel::firstOrCreate(
            ['slug' => $slug],
            array_merge($factoryDefaults(), $attributes),
        );
    }
}