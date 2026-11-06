<?php

declare(strict_types=1);

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\RolePermissionGrantsInterface;
use Illuminate\Database\Migrations\Migration;

/**
 * The editor role gets knowledge.manage (the site's own help guides, hiding and starring the official ones)
 * like a new install does through RolesSeeder; the grant goes through the core so the role history shows it.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(RolePermissionGrantsInterface::class)->grantToRole(
            'editor',
            new PermissionDefinitionDTO(
                key: 'knowledge.manage',
                label: 'Gestionar las guías de ayuda',
                resource: 'knowledge',
                action: 'manage',
                defaultRoles: ['editor'],
            ),
        );
    }

    public function down(): void
    {
        // Nothing to undo: permission grants are never taken away by a migration
    }
};
