<?php

declare(strict_types=1);

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\RolePermissionGrantsInterface;
use Illuminate\Database\Migrations\Migration;

/**
 * The editor role of an existing site gets media.view_any (looking at the panel media library), never
 * media.delete: deleting is granted by hand. The grant goes through the core so the role history shows it.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(RolePermissionGrantsInterface::class)->grantToRole(
            'editor',
            new PermissionDefinitionDTO(
                key: 'media.view_any',
                label: 'Ver la biblioteca de medios',
                resource: 'media',
                action: 'view_any',
                defaultRoles: ['editor'],
            ),
        );
    }

    public function down(): void
    {
        // Nothing to undo: permission grants are never taken away by a migration
    }
};
