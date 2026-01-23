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
        HeroSlideModel::factory()->active()->withOrder(1)->create([
            'title' => 'Bienvenido a GuildForge',
            'subtitle' => 'Tu asociación de juegos de mesa y rol',
            'button_text' => 'Conócenos',
            'button_url' => '/nosotros',
        ]);

        HeroSlideModel::factory()->active()->withOrder(2)->create([
            'title' => 'Próximos eventos',
            'subtitle' => 'Torneos, partidas y talleres',
            'button_text' => 'Ver eventos',
            'button_url' => '/eventos',
        ]);

        HeroSlideModel::factory()->active()->withOrder(3)->create([
            'title' => 'Galería de fotos',
            'subtitle' => 'Revive nuestros mejores momentos',
            'button_text' => 'Ver galería',
            'button_url' => '/galeria',
        ]);
    }
}
