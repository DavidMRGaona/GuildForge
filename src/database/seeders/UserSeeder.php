<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;

final class UserSeeder extends Seeder
{
    /**
     * Seed the users table.
     */
    public function run(AuthorizationServiceInterface $authorization, MemberRoleServiceInterface $memberRole): void
    {
        // Get roles
        $adminRole = RoleModel::where('name', 'admin')->first();
        $editorRole = RoleModel::where('name', 'editor')->first();

        // Create or get admin user. Roles live in the user_role pivot, given below: raw() only
        // returns column values, so a factory role state would have no effect here
        $adminAttributes = UserModel::factory()->raw([
            'name' => 'Admin User',
            'display_name' => 'Administrator',
        ]);
        unset($adminAttributes['email']); // Remove factory-generated email
        $adminUser = UserModel::firstOrCreate(
            ['email' => 'admin@example.local'],
            $adminAttributes,
        );
        if ($adminRole) {
            $authorization->assignRole($adminUser, 'admin');
        }

        // "member" is the base role of every account, admins and editors included. Given like
        // every other way of creating a user (it logs a warning when the role is missing)
        $memberRole->assignTo($adminUser);

        // Create or get editor user
        $editorAttributes = UserModel::factory()->raw([
            'name' => 'Editor User',
            'display_name' => 'Editor',
        ]);
        unset($editorAttributes['email']);
        $editorUser = UserModel::firstOrCreate(
            ['email' => 'editor@example.local'],
            $editorAttributes,
        );
        if ($editorRole) {
            $authorization->assignRole($editorUser, 'editor');
        }

        $memberRole->assignTo($editorUser);

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
        $memberRole->assignTo($memberUser);

        // Create additional random members (only if less than 8 users exist)
        $existingCount = UserModel::count();
        if ($existingCount < 8) {
            $randomUsers = UserModel::factory()->count(8 - $existingCount)->create();
            foreach ($randomUsers as $user) {
                $memberRole->assignTo($user);
            }
        }
    }
}
