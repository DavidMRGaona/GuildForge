<?php

declare(strict_types=1);

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\RolePermissionGrantsInterface;
use Illuminate\Database\Migrations\Migration;

/**
 * The editor role gets search.view_stats (searches without results on the dashboard) like a
 * new install does through RolesSeeder; the grant goes through the core so the role history shows it.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(RolePermissionGrantsInterface::class)->grantToRole(
            'editor',
            new PermissionDefinitionDTO(
                key: 'search.view_stats',
                label: 'Ver estadísticas de búsqueda',
                resource: 'search',
                action: 'view_stats',
                defaultRoles: ['editor'],
            ),
        );
    }

    public function down(): void
    {
        // Nothing to undo: permission grants are never taken away by a migration
    }
};
