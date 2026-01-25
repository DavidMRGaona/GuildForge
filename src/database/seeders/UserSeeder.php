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

        // Create or get admin user
        $adminAttributes = UserModel::factory()->admin()->raw([
            'name' => 'Admin User',
            'display_name' => 'Administrator',
        ]);
        unset($adminAttributes['email']); // Remove factory-generated email
        $adminUser = UserModel::firstOrCreate(
            ['email' => 'admin@example.local'],
            $adminAttributes,
        );
        if ($adminRole) {
            $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        // Create or get editor user
        $editorAttributes = UserModel::factory()->editor()->raw([
            'name' => 'Editor User',
            'display_name' => 'Editor',
        ]);
        unset($editorAttributes['email']);
        $editorUser = UserModel::firstOrCreate(
            ['email' => 'editor@example.local'],
            $editorAttributes,
        );
        if ($editorRole) {
            $editorUser->roles()->syncWithoutDetaching([$editorRole->id]);
        }

        // Create or get member user
        $memberAttributes = UserModel::factory()->raw([
            'name' => 'Member User',
            'display_name' => 'Member',
        ]);
        unset($memberAttributes['email']);
        $memberUser = UserModel::firstOrCreate(
            ['email' => 'member@example.local'],
            $memberAttributes,
        );
        if ($memberRole) {
            $memberUser->roles()->syncWithoutDetaching([$memberRole->id]);
        }

        // Create additional random members (only if less than 8 users exist)
        $existingCount = UserModel::count();
        if ($existingCount < 8) {
            $randomUsers = UserModel::factory()->count(8 - $existingCount)->create();
            if ($memberRole) {
                foreach ($randomUsers as $user) {
                    $user->roles()->syncWithoutDetaching([$memberRole->id]);
                }
            }
        }
    }
}
