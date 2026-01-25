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
            array_merge(
                HeroSlideModel::factory()->active()->withOrder(1)->raw(),
                [
                    'title' => 'Bienvenido a GuildForge',
                    'subtitle' => 'Tu asociación de juegos de mesa y rol',
                    'button_text' => 'Conócenos',
                ],
            ),
        );

        HeroSlideModel::firstOrCreate(
            ['button_url' => '/eventos'],
            array_merge(
                HeroSlideModel::factory()->active()->withOrder(2)->raw(),
                [
                    'title' => 'Próximos eventos',
                    'subtitle' => 'Torneos, partidas y talleres',
                    'button_text' => 'Ver eventos',
                ],
            ),
        );

        HeroSlideModel::firstOrCreate(
            ['button_url' => '/galeria'],
            array_merge(
                HeroSlideModel::factory()->active()->withOrder(3)->raw(),
                [
                    'title' => 'Galería de fotos',
                    'subtitle' => 'Revive nuestros mejores momentos',
                    'button_text' => 'Ver galería',
                ],
            ),
        );
    }
}
