<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Migrates existing users from the enum role column to the new
     * user_role pivot table. This migration is idempotent and can
     * be run multiple times safely.
     */
    public function up(): void
    {
        // Get all roles that exist in the roles table
        $roles = DB::table('roles')->pluck('id', 'name');

        if ($roles->isEmpty()) {
            // Roles haven't been seeded yet, skip migration
            return;
        }

        // Get all users with their current role from the enum column
        $users = DB::table('users')
            ->whereNotNull('role')
            ->select(['id', 'role'])
            ->get();

        foreach ($users as $user) {
            $roleName = $user->role;

            if (! isset($roles[$roleName])) {
                // Role doesn't exist, skip this user
                continue;
            }

            $roleId = $roles[$roleName];

            // Check if user already has this role assigned
            $exists = DB::table('user_role')
                ->where('user_id', $user->id)
                ->where('role_id', $roleId)
                ->exists();

            if (! $exists) {
                DB::table('user_role')->insert([
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: This doesn't restore the original enum values since
     * the user_role table is the source of truth after migration.
     */
    public function down(): void
    {
        // We don't reverse this migration as it would lose data
        // The role column is preserved during forward migration
    }
};
