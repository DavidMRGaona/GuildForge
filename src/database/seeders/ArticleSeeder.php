<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
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

        // 3 published articles with real Spanish content
        ArticleModel::factory()->published()->create([
            'title' => 'Guía de iniciación para nuevos socios',
            'slug' => 'guia-iniciacion-nuevos-socios',
            'content' => 'Bienvenido a nuestra asociación. En esta guía te explicamos todo lo que necesitas saber para empezar: cómo participar en nuestras actividades, los horarios habituales, las cuotas de socio y los beneficios que obtendrás. También te presentamos a los miembros del equipo directivo y te explicamos cómo funcionan nuestras sesiones regulares. No dudes en preguntar cualquier duda a los miembros veteranos.',
            'excerpt' => 'Todo lo que necesitas saber para empezar como socio.',
            'author_id' => $editors->random()->id,
        ]);

        ArticleModel::factory()->published()->create([
            'title' => 'Técnicas y consejos para principiantes',
            'slug' => 'tecnicas-consejos-principiantes',
            'content' => 'Empezar en cualquier hobby puede parecer intimidante al principio, pero con las técnicas adecuadas y algo de práctica, cualquiera puede conseguir resultados impresionantes. En este artículo cubrimos los conceptos básicos que todo principiante debería conocer. También te recomendamos los materiales esenciales para empezar y te damos consejos prácticos basados en nuestra experiencia. Recuerda que la paciencia es la clave del éxito.',
            'excerpt' => 'Aprende las técnicas básicas paso a paso.',
            'author_id' => $editors->random()->id,
        ]);

        ArticleModel::factory()->published()->create([
            'title' => 'Crónica del torneo regional 2024',
            'slug' => 'cronica-torneo-regional-2024',
            'content' => 'El pasado fin de semana celebramos nuestro torneo regional 2024 con una participación récord de 32 participantes de toda la comunidad. Las actividades fueron emocionantes y el nivel muy alto. El primer puesto fue para Juan García, seguido de María López y Pedro Martínez. Agradecemos a todos los participantes y voluntarios que hicieron posible este evento. ¡Nos vemos en el próximo torneo!',
            'excerpt' => 'Resumen y resultados del torneo regional 2024.',
            'author_id' => $editors->random()->id,
        ]);

        // 2 draft articles
        ArticleModel::factory()->draft()->create([
            'title' => 'Próximos eventos de la temporada',
            'slug' => 'proximos-eventos-temporada',
            'content' => 'Borrador: Calendario de eventos para la próxima temporada. Incluir fechas de torneos, talleres y actividades organizadas.',
            'excerpt' => 'Calendario de actividades de la asociación.',
            'author_id' => $editors->random()->id,
        ]);

        ArticleModel::factory()->draft()->create([
            'title' => 'Entrevista al campeón regional',
            'slug' => 'entrevista-campeon-regional',
            'content' => 'Borrador: Entrevista pendiente de realizar con el ganador del torneo regional.',
            'excerpt' => 'Conversamos con el ganador del último torneo.',
            'author_id' => $editors->random()->id,
        ]);

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
