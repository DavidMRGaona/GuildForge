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
        $adminUser = UserModel::firstOrCreate(
            ['email' => 'admin@example.local'],
            $this->accountAttributes('Admin User', 'Administrator'),
        );
        if ($adminRole) {
            $authorization->assignRole($adminUser, 'admin');
        }

        // "member" is the base role of every account, admins and editors included. Given like
        // every other way of creating a user (it logs a warning when the role is missing)
        $memberRole->assignTo($adminUser);

        // Create or get editor user
        $editorUser = UserModel::firstOrCreate(
            ['email' => 'editor@example.local'],
            $this->accountAttributes('Editor User', 'Editor'),
        );
        if ($editorRole) {
            $authorization->assignRole($editorUser, 'editor');
        }

        $memberRole->assignTo($editorUser);

        // Create or get member user
        $memberUser = UserModel::firstOrCreate(
            ['email' => 'member@example.local'],
            $this->accountAttributes('Member User', 'Member'),
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

    /**
     * Factory column values for a seeded account, without the generated email (the caller sets it)
     *
     * @return array<string, mixed>
     */
    private function accountAttributes(string $name, string $displayName): array
    {
        $attributes = UserModel::factory()->raw(['name' => $name, 'display_name' => $displayName]);
        unset($attributes['email']);

        // raw() types its keys as int|string; the attributes are column names
        return array_filter($attributes, is_string(...), ARRAY_FILTER_USE_KEY);
    }
}
