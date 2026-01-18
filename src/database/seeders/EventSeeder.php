<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Seeder;

final class EventSeeder extends Seeder
{
    /**
     * Seed the events table.
     */
    public function run(): void
    {
        // Published upcoming events
        EventModel::factory()->published()->upcoming()->create([
            'title' => 'Torneo mensual',
            'slug' => 'torneo-mensual',
            'description' => 'Gran torneo mensual con premios para los tres primeros clasificados.',
            'location' => 'Sala principal',
        ]);

        EventModel::factory()->published()->upcoming()->create([
            'title' => 'Sesión de juego abierta',
            'slug' => 'sesion-juego-abierta',
            'description' => 'Sesión abierta para principiantes y veteranos. Materiales disponibles.',
            'location' => 'Sala de juegos',
        ]);

        EventModel::factory()->published()->upcoming()->create([
            'title' => 'Taller de iniciación',
            'slug' => 'taller-iniciacion',
            'description' => 'Aprende las bases con nuestro taller de iniciación. Materiales incluidos.',
            'location' => 'Sala de talleres',
        ]);

        // Draft upcoming events
        EventModel::factory()->draft()->upcoming()->create([
            'title' => 'Nueva liga',
            'slug' => 'nueva-liga',
            'description' => 'Nueva liga de la asociación. Inscripciones abiertas pronto.',
            'location' => 'Sala principal',
        ]);

        // Published past events
        EventModel::factory()->published()->past()->create([
            'title' => 'Campeonato regional 2024',
            'slug' => 'campeonato-regional-2024',
            'description' => 'Campeonato regional con participantes de toda la comunidad.',
            'location' => 'Centro de convenciones',
        ]);

        EventModel::factory()->published()->past()->create([
            'title' => 'Jornada de puertas abiertas',
            'slug' => 'jornada-puertas-abiertas',
            'description' => 'Ven a conocer nuestra asociación y todas las actividades que organizamos.',
            'location' => 'Local de la asociación',
        ]);

        // Additional random events
        EventModel::factory()->published()->upcoming()->count(3)->create();
        EventModel::factory()->draft()->upcoming()->count(2)->create();
        EventModel::factory()->published()->past()->count(4)->create();
    }
}
