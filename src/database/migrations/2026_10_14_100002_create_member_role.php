<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "member" is the base role of every account. RolesSeeder creates it, but it runs after the
 * migrations: on a new install, module migrations that grant it permissions would find no
 * role. Create it here when missing, with the values RolesSeeder::createRole() uses (whose
 * firstOrCreate then finds it), and never touch an existing one. Written without Eloquent;
 * the id is a UUIDv7, as HasUuids generates for RoleModel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('roles')->where('name', 'member')->exists()) {
            return;
        }

        DB::table('roles')->insert([
            'id' => (string) Str::uuid7(),
            'name' => 'member',
            'display_name' => 'Member',
            'description' => 'Basic member access',
            'is_protected' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Nothing to undo: the role may already be assigned to users, and role data is never deleted
    }
};
