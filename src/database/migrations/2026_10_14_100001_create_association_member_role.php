<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Database\Migrations\Migration;

/**
 * "socio" is the default association member role (site setting association_member_role).
 * Sites created it by hand so far: create it only when no role has that name, and never
 * touch an existing one. Created like RolesSeeder does, so the id is generated the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        RoleModel::query()->firstOrCreate(
            ['name' => 'socio'],
            [
                'display_name' => 'Socio',
                'description' => 'Miembro de la asociación',
                'is_protected' => false,
            ],
        );
    }

    public function down(): void
    {
        // Nothing to undo: the role may already be assigned to users, and role data is never deleted
    }
};
