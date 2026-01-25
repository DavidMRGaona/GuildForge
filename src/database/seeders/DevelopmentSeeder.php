<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds demo/test data for development and testing environments.
 * NEVER run in production - creates fake users and demo content.
 */
final class DevelopmentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            EventSeeder::class,
            ArticleSeeder::class,
            GallerySeeder::class,
        ]);
    }
}
