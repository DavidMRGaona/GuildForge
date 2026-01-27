<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds essential data required for the application to function.
 * Safe to run in production. Does NOT create demo users or content.
 */
final class ProductionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            TagSeeder::class,
            HeroSlideSeeder::class,
            SettingsSeeder::class,
            LegalSettingsSeeder::class,
            MenuItemSeeder::class,
        ]);
    }
}
