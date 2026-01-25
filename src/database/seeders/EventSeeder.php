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
        $torneoMensual = $this->firstOrCreateEvent(
            'torneo-mensual',
            [
                'title' => 'Torneo mensual',
                'description' => 'Gran torneo mensual con premios para los tres primeros clasificados.',
                'location' => 'Sala principal',
            ],
            fn () => EventModel::factory()->published()->upcoming()->raw(),
        );
        $torneoMensual->tags()->syncWithoutDetaching(array_filter([$warhammer40kTag?->id, $torneoTag?->id]));

        $sesionAbierta = $this->firstOrCreateEvent(
            'sesion-juego-abierta',
            [
                'title' => 'Sesión de juego abierta',
                'description' => 'Sesión abierta para principiantes y veteranos. Materiales disponibles.',
                'location' => 'Sala de juegos',
            ],
            fn () => EventModel::factory()->published()->upcoming()->raw(),
        );
        $sesionAbierta->tags()->syncWithoutDetaching(array_filter([$wargamesTag?->id, $sesionAbiertaTag?->id]));

        $tallerIniciacion = $this->firstOrCreateEvent(
            'taller-iniciacion',
            [
                'title' => 'Taller de iniciación',
                'description' => 'Aprende las bases con nuestro taller de iniciación. Materiales incluidos.',
                'location' => 'Sala de talleres',
            ],
            fn () => EventModel::factory()->published()->upcoming()->raw(),
        );
        $tallerIniciacion->tags()->syncWithoutDetaching(array_filter([$dndTag?->id, $tallerTag?->id]));

        // Draft upcoming events
        $this->firstOrCreateEvent(
            'nueva-liga',
            [
                'title' => 'Nueva liga',
                'description' => 'Nueva liga de la asociación. Inscripciones abiertas pronto.',
                'location' => 'Sala principal',
            ],
            fn () => EventModel::factory()->draft()->upcoming()->raw(),
        );

        // Published past events
        $campeonatoRegional = $this->firstOrCreateEvent(
            'campeonato-regional-2024',
            [
                'title' => 'Campeonato regional 2024',
                'description' => 'Campeonato regional con participantes de toda la comunidad.',
                'location' => 'Centro de convenciones',
            ],
            fn () => EventModel::factory()->published()->past()->raw(),
        );
        $campeonatoRegional->tags()->syncWithoutDetaching(array_filter([$warhammer40kTag?->id, $torneoTag?->id]));

        $jornadaPuertasAbiertas = $this->firstOrCreateEvent(
            'jornada-puertas-abiertas',
            [
                'title' => 'Jornada de puertas abiertas',
                'description' => 'Ven a conocer nuestra asociación y todas las actividades que organizamos.',
                'location' => 'Local de la asociación',
            ],
            fn () => EventModel::factory()->published()->past()->raw(),
        );
        $jornadaPuertasAbiertas->tags()->syncWithoutDetaching(array_filter([$juegosRolTag?->id, $wargamesTag?->id]));

        // Additional random events (only if we have few events)
        $existingCount = EventModel::count();
        if ($existingCount < 15) {
            EventModel::factory()->published()->upcoming()->count(3)->create();
            EventModel::factory()->draft()->upcoming()->count(2)->create();
            EventModel::factory()->published()->past()->count(4)->create();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  callable(): array<string, mixed>  $factoryDefaults
     */
    private function firstOrCreateEvent(string $slug, array $attributes, callable $factoryDefaults): EventModel
    {
        return EventModel::firstOrCreate(
            ['slug' => $slug],
            array_merge($factoryDefaults(), $attributes),
        );
    }
}