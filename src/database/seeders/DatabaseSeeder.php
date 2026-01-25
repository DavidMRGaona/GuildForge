<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Master orchestrator for database seeding.
 *
 * - Always seeds production data (roles, tags, settings, hero slides)
 * - Only seeds development data (users, events, articles, galleries) in local/testing
 */
final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(ProductionSeeder::class);

        if ($this->shouldSeedDevelopmentData()) {
            $this->call(DevelopmentSeeder::class);
        }
    }

    private function shouldSeedDevelopmentData(): bool
    {
        return app()->environment(['local', 'testing']);
    }
}
