<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Illuminate\Database\Seeder;

final class EventSeeder extends Seeder
{
    /**
     * Seed the events table.
     */
    public function run(): void
    {
        // Get tags for attaching
        $warhammer40kTag = TagModel::where('slug', 'warhammer-40k')->first();
        $torneoTag = TagModel::where('slug', 'torneo')->first();
        $sesionAbiertaTag = TagModel::where('slug', 'sesion-abierta')->first();
        $tallerTag = TagModel::where('slug', 'taller')->first();
        $wargamesTag = TagModel::where('slug', 'wargames')->first();
        $juegosRolTag = TagModel::where('slug', 'juegos-de-rol')->first();
        $dndTag = TagModel::where('slug', 'dnd')->first();

        // Published upcoming events
        $torneoMensual = EventModel::factory()->published()->upcoming()->create([
            'title' => 'Torneo mensual',
            'slug' => 'torneo-mensual',
            'description' => 'Gran torneo mensual con premios para los tres primeros clasificados.',
            'location' => 'Sala principal',
        ]);
        $torneoMensual->tags()->attach(array_filter([$warhammer40kTag?->id, $torneoTag?->id]));

        $sesionAbierta = EventModel::factory()->published()->upcoming()->create([
            'title' => 'Sesión de juego abierta',
            'slug' => 'sesion-juego-abierta',
            'description' => 'Sesión abierta para principiantes y veteranos. Materiales disponibles.',
            'location' => 'Sala de juegos',
        ]);
        $sesionAbierta->tags()->attach(array_filter([$wargamesTag?->id, $sesionAbiertaTag?->id]));

        $tallerIniciacion = EventModel::factory()->published()->upcoming()->create([
            'title' => 'Taller de iniciación',
            'slug' => 'taller-iniciacion',
            'description' => 'Aprende las bases con nuestro taller de iniciación. Materiales incluidos.',
            'location' => 'Sala de talleres',
        ]);
        $tallerIniciacion->tags()->attach(array_filter([$dndTag?->id, $tallerTag?->id]));

        // Draft upcoming events
        EventModel::factory()->draft()->upcoming()->create([
            'title' => 'Nueva liga',
            'slug' => 'nueva-liga',
            'description' => 'Nueva liga de la asociación. Inscripciones abiertas pronto.',
            'location' => 'Sala principal',
        ]);

        // Published past events
        $campeonatoRegional = EventModel::factory()->published()->past()->create([
            'title' => 'Campeonato regional 2024',
            'slug' => 'campeonato-regional-2024',
            'description' => 'Campeonato regional con participantes de toda la comunidad.',
            'location' => 'Centro de convenciones',
        ]);
        $campeonatoRegional->tags()->attach(array_filter([$warhammer40kTag?->id, $torneoTag?->id]));

        $jornadaPuertasAbiertas = EventModel::factory()->published()->past()->create([
            'title' => 'Jornada de puertas abiertas',
            'slug' => 'jornada-puertas-abiertas',
            'description' => 'Ven a conocer nuestra asociación y todas las actividades que organizamos.',
            'location' => 'Local de la asociación',
        ]);
        $jornadaPuertasAbiertas->tags()->attach(array_filter([$juegosRolTag?->id, $wargamesTag?->id]));

        // Additional random events
        EventModel::factory()->published()->upcoming()->count(3)->create();
        EventModel::factory()->draft()->upcoming()->count(2)->create();
        EventModel::factory()->published()->past()->count(4)->create();
    }
}
