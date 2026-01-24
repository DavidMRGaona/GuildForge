<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

final class UserSeeder extends Seeder
{
    /**
     * Seed the users table.
     */
    public function run(): void
    {
        // Create admin user
        UserModel::factory()->admin()->create([
            'name' => 'Admin User',
            'display_name' => 'Administrator',
            'email' => 'admin@example.local',
        ]);

        // Create editor user
        UserModel::factory()->editor()->create([
            'name' => 'Editor User',
            'display_name' => 'Editor',
            'email' => 'editor@example.local',
        ]);

        // Create member user
        UserModel::factory()->create([
            'name' => 'Member User',
            'display_name' => 'Member',
            'email' => 'member@example.local',
        ]);

        // Create additional random members
        UserModel::factory()->count(5)->create();
    }
}
