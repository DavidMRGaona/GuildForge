<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "socio" is the default association member role (site setting association_member_role).
 * Sites created it by hand so far: create it only when no role has that name, and never
 * touch an existing one. Written without Eloquent so later model changes cannot break it;
 * the id is a UUIDv7, as HasUuids generates for RoleModel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('roles')->where('name', 'socio')->exists()) {
            return;
        }

        DB::table('roles')->insert([
            'id' => (string) Str::uuid7(),
            'name' => 'socio',
            'display_name' => 'Socio',
            'description' => 'Miembro de la asociación',
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
