<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\SettingModel;
use Illuminate\Database\Seeder;

final class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activities = [
            ['icon' => 'dice', 'title' => 'Juegos de rol', 'description' => 'Partidas semanales de D&D, Pathfinder, Call of Cthulhu y más sistemas.'],
            ['icon' => 'sword', 'title' => 'Wargames', 'description' => 'Batallas épicas con Warhammer, Infinity, Bolt Action y otros sistemas.'],
            ['icon' => 'puzzle', 'title' => 'Juegos de mesa', 'description' => 'Noches de juegos de mesa modernos y clásicos para todos los gustos.'],
            ['icon' => 'trophy', 'title' => 'Torneos', 'description' => 'Competiciones mensuales con premios y clasificaciones.'],
            ['icon' => 'calendar', 'title' => 'Eventos especiales', 'description' => 'Jornadas temáticas, maratones de juego y quedadas especiales.'],
            ['icon' => 'book', 'title' => 'Biblioteca', 'description' => 'Préstamo de juegos, manuales y material para que pruebes antes de comprar.'],
            ['icon' => 'map', 'title' => 'Campañas', 'description' => 'Campañas narrativas de larga duración con historia continuada.'],
            ['icon' => 'users', 'title' => 'Comunidad', 'description' => 'Un espacio acogedor donde compartir tu pasión con gente afín.'],
        ];

        $joinSteps = [
            ['title' => 'Ven a conocernos', 'description' => 'Asiste a una de nuestras sesiones abiertas.'],
            ['title' => 'Rellena el formulario', 'description' => 'Completa el formulario de inscripción.'],
            ['title' => 'Paga la cuota', 'description' => 'Realiza el pago de la cuota anual.'],
            ['title' => '¡Empieza a jugar!', 'description' => null],
        ];

        $settings = [
            'location_name' => env('APP_NAME', 'GuildForge').' HQ',
            'location_address' => 'Your address here',
            'location_lat' => '40.4168',
            'location_lng' => '-3.7038',
            'location_zoom' => '15',
            'guild_name' => env('APP_NAME', 'GuildForge'),
            'about_hero_image' => '',
            'about_tagline' => 'Tu comunidad de juegos de rol y wargames',
            'about_history' => '<p><strong>GuildForge</strong> nació en 2010 como un pequeño grupo de amigos apasionados por los juegos de rol y los wargames. Lo que comenzó como partidas casuales en el salón de uno de nuestros fundadores, pronto se convirtió en algo más grande.</p><p>En 2012, nos constituimos oficialmente como asociación, con el objetivo de promover los juegos de mesa, rol y wargames en nuestra comunidad. Desde entonces, hemos organizado cientos de eventos, torneos y jornadas que han reunido a jugadores de todas las edades y experiencias.</p><p>Hoy somos más de <strong>50 miembros activos</strong> y seguimos creciendo. Nuestra sede se ha convertido en un punto de encuentro para la comunidad gamer de la zona, donde cada semana se respira el espíritu de aventura y competición sana.</p>',
            'about_activities' => json_encode($activities),
            'join_steps' => json_encode($joinSteps),
            'contact_email' => 'info@guildforge.es',
            'contact_phone' => '+34 612 345 678',
            'contact_address' => 'Calle de la Forja, 42, Local 3, 28001 Madrid',
            'anonymized_user_name' => 'Anónimo',
        ];

        foreach ($settings as $key => $value) {
            SettingModel::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
