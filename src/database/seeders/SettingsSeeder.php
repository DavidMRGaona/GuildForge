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
        $settings = [
            'location_name' => env('APP_NAME', 'Association') . ' HQ',
            'location_address' => 'Your address here',
            'location_lat' => '40.4168',
            'location_lng' => '-3.7038',
            'location_zoom' => '15',
            'association_name' => env('APP_NAME', 'Runesword'),
            'about_history' => '<p><strong>Runesword</strong> nació en 2010 como un pequeño grupo de amigos apasionados por los juegos de rol y los wargames. Lo que comenzó como partidas casuales en el salón de uno de nuestros fundadores, pronto se convirtió en algo más grande.</p><p>En 2012, nos constituimos oficialmente como asociación, con el objetivo de promover los juegos de mesa, rol y wargames en nuestra comunidad. Desde entonces, hemos organizado cientos de eventos, torneos y jornadas que han reunido a jugadores de todas las edades y experiencias.</p><p>Hoy somos más de <strong>50 miembros activos</strong> y seguimos creciendo. Nuestra sede se ha convertido en un punto de encuentro para la comunidad gamer de la zona, donde cada semana se respira el espíritu de aventura y competición sana.</p>',
            'contact_email' => 'info@runesword.es',
            'contact_phone' => '+34 612 345 678',
            'contact_address' => 'Calle de la Espada Rúnica, 42, Local 3, 28001 Madrid',
        ];

        foreach ($settings as $key => $value) {
            SettingModel::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
