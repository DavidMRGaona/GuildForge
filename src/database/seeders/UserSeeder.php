<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

final class UserSeeder extends Seeder
{
    /**
     * Seed the users table.
     */
    public function run(): void
    {
        // Get roles
        $adminRole = RoleModel::where('name', 'admin')->first();
        $editorRole = RoleModel::where('name', 'editor')->first();
        $memberRole = RoleModel::where('name', 'member')->first();

        // Create admin user
        $adminUser = UserModel::factory()->admin()->create([
            'name' => 'Admin User',
            'display_name' => 'Administrator',
            'email' => 'admin@example.local',
        ]);
        if ($adminRole) {
            $adminUser->roles()->attach($adminRole->id);
        }

        // Create editor user
        $editorUser = UserModel::factory()->editor()->create([
            'name' => 'Editor User',
            'display_name' => 'Editor',
            'email' => 'editor@example.local',
        ]);
        if ($editorRole) {
            $editorUser->roles()->attach($editorRole->id);
        }

        // Create member user
        $memberUser = UserModel::factory()->create([
            'name' => 'Member User',
            'display_name' => 'Member',
            'email' => 'member@example.local',
        ]);
        if ($memberRole) {
            $memberUser->roles()->attach($memberRole->id);
        }

        // Create additional random members
        $randomUsers = UserModel::factory()->count(5)->create();
        if ($memberRole) {
            foreach ($randomUsers as $user) {
                $user->roles()->attach($memberRole->id);
            }
        }
    }
}
