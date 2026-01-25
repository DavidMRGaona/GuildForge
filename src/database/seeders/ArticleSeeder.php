<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

final class ArticleSeeder extends Seeder
{
    /**
     * Seed the articles table.
     */
    public function run(): void
    {
        $editors = UserModel::query()
            ->whereIn('role', ['admin', 'editor'])
            ->get();

        if ($editors->isEmpty()) {
            $editors = collect([UserModel::factory()->editor()->create()]);
        }

        // Get tags for attaching
        $warhammer40kTag = TagModel::where('slug', 'warhammer-40k')->first();
        $wargamesTag = TagModel::where('slug', 'wargames')->first();
        $juegosRolTag = TagModel::where('slug', 'juegos-de-rol')->first();
        $juegosMesaTag = TagModel::where('slug', 'juegos-de-mesa')->first();

        // 3 published articles with real Spanish content
        $guiaIniciacion = $this->firstOrCreateArticle(
            'guia-iniciacion-nuevos-socios',
            [
                'title' => 'Guía de iniciación para nuevos socios',
                'content' => 'Bienvenido a nuestra asociación. En esta guía te explicamos todo lo que necesitas saber para empezar: cómo participar en nuestras actividades, los horarios habituales, las cuotas de socio y los beneficios que obtendrás. También te presentamos a los miembros del equipo directivo y te explicamos cómo funcionan nuestras sesiones regulares. No dudes en preguntar cualquier duda a los miembros veteranos.',
                'excerpt' => 'Todo lo que necesitas saber para empezar como socio.',
                'author_id' => $editors->random()->id,
            ],
            fn () => ArticleModel::factory()->published()->raw(),
        );
        $guiaIniciacion->tags()->syncWithoutDetaching(array_filter([$wargamesTag?->id, $juegosRolTag?->id, $juegosMesaTag?->id]));

        $tecnicasConsejos = $this->firstOrCreateArticle(
            'tecnicas-consejos-principiantes',
            [
                'title' => 'Técnicas y consejos para principiantes',
                'content' => 'Empezar en cualquier hobby puede parecer intimidante al principio, pero con las técnicas adecuadas y algo de práctica, cualquiera puede conseguir resultados impresionantes. En este artículo cubrimos los conceptos básicos que todo principiante debería conocer. También te recomendamos los materiales esenciales para empezar y te damos consejos prácticos basados en nuestra experiencia. Recuerda que la paciencia es la clave del éxito.',
                'excerpt' => 'Aprende las técnicas básicas paso a paso.',
                'author_id' => $editors->random()->id,
            ],
            fn () => ArticleModel::factory()->published()->raw(),
        );
        $tecnicasConsejos->tags()->syncWithoutDetaching(array_filter([$warhammer40kTag?->id]));

        $cronicaTorneo = $this->firstOrCreateArticle(
            'cronica-torneo-regional-2024',
            [
                'title' => 'Crónica del torneo regional 2024',
                'content' => 'El pasado fin de semana celebramos nuestro torneo regional 2024 con una participación récord de 32 participantes de toda la comunidad. Las actividades fueron emocionantes y el nivel muy alto. El primer puesto fue para Juan García, seguido de María López y Pedro Martínez. Agradecemos a todos los participantes y voluntarios que hicieron posible este evento. ¡Nos vemos en el próximo torneo!',
                'excerpt' => 'Resumen y resultados del torneo regional 2024.',
                'author_id' => $editors->random()->id,
            ],
            fn () => ArticleModel::factory()->published()->raw(),
        );
        $cronicaTorneo->tags()->syncWithoutDetaching(array_filter([$warhammer40kTag?->id, $wargamesTag?->id]));

        // 2 draft articles
        $this->firstOrCreateArticle(
            'proximos-eventos-temporada',
            [
                'title' => 'Próximos eventos de la temporada',
                'content' => 'Borrador: Calendario de eventos para la próxima temporada. Incluir fechas de torneos, talleres y actividades organizadas.',
                'excerpt' => 'Calendario de actividades de la asociación.',
                'author_id' => $editors->random()->id,
            ],
            fn () => ArticleModel::factory()->draft()->raw(),
        );

        $this->firstOrCreateArticle(
            'entrevista-campeon-regional',
            [
                'title' => 'Entrevista al campeón regional',
                'content' => 'Borrador: Entrevista pendiente de realizar con el ganador del torneo regional.',
                'excerpt' => 'Conversamos con el ganador del último torneo.',
                'author_id' => $editors->random()->id,
            ],
            fn () => ArticleModel::factory()->draft()->raw(),
        );

        // Random articles (only if we have few articles)
        $existingCount = ArticleModel::count();
        if ($existingCount < 13) {
            // 5 random published articles
            ArticleModel::factory()->published()->count(5)->create([
                'author_id' => fn () => $editors->random()->id,
            ]);

            // 3 random draft articles
            ArticleModel::factory()->draft()->count(3)->create([
                'author_id' => fn () => $editors->random()->id,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  callable(): array<string, mixed>  $factoryDefaults
     */
    private function firstOrCreateArticle(string $slug, array $attributes, callable $factoryDefaults): ArticleModel
    {
        return ArticleModel::firstOrCreate(
            ['slug' => $slug],
            array_merge($factoryDefaults(), $attributes),
        );
    }
}