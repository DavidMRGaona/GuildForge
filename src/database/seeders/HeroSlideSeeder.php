<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use Illuminate\Database\Seeder;

final class HeroSlideSeeder extends Seeder
{
    /**
     * Seed the hero_slides table.
     */
    public function run(): void
    {
        HeroSlideModel::firstOrCreate(
            ['button_url' => '/nosotros'],
            [
                'title' => 'Bienvenido a GuildForge',
                'subtitle' => 'Tu asociación de juegos de mesa y rol',
                'button_text' => 'Conócenos',
                'image_public_id' => null,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        HeroSlideModel::firstOrCreate(
            ['button_url' => '/eventos'],
            [
                'title' => 'Próximos eventos',
                'subtitle' => 'Torneos, partidas y talleres',
                'button_text' => 'Ver eventos',
                'image_public_id' => null,
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        HeroSlideModel::firstOrCreate(
            ['button_url' => '/galeria'],
            [
                'title' => 'Galería de fotos',
                'subtitle' => 'Revive nuestros mejores momentos',
                'button_text' => 'Ver galería',
                'image_public_id' => null,
                'is_active' => true,
                'sort_order' => 3,
            ],
        );
    }
}
