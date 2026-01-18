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
        ];

        foreach ($settings as $key => $value) {
            SettingModel::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
