<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Infrastructure\Authorization\CorePermissionDefinitions;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Database\Seeder;

final class RolesSeeder extends Seeder
{
    /**
     * Seed the roles and permissions tables.
     */
    public function run(): void
    {
        // First, sync permissions to database
        $this->syncPermissions();

        // Create roles
        $adminRole = $this->createRole(
            name: 'admin',
            displayName: 'Administrator',
            description: 'Full access to all features and settings',
            isProtected: true,
        );

        $editorRole = $this->createRole(
            name: 'editor',
            displayName: 'Editor',
            description: 'Can manage content (events, articles, galleries)',
            isProtected: false,
        );

        $memberRole = $this->createRole(
            name: 'member',
            displayName: 'Member',
            description: 'Basic member access',
            isProtected: false,
        );

        // Assign permissions to editor role based on defaultRoles
        $this->assignDefaultPermissions($editorRole);

        // Admin gets all permissions implicitly via isAdmin() check,
        // but we can also assign them explicitly for clarity
        $this->assignAllPermissions($adminRole);
    }

    private function syncPermissions(): void
    {
        /** @var PermissionRegistryInterface $registry */
        $registry = app(PermissionRegistryInterface::class);
        $registry->registerMany(CorePermissionDefinitions::all());
        $registry->syncToDatabase();
    }

    private function createRole(
        string $name,
        string $displayName,
        string $description,
        bool $isProtected,
    ): RoleModel {
        return RoleModel::firstOrCreate(
            ['name' => $name],
            [
                'display_name' => $displayName,
                'description' => $description,
                'is_protected' => $isProtected,
            ]
        );
    }

    private function assignDefaultPermissions(RoleModel $role): void
    {
        $definitions = CorePermissionDefinitions::all();
        $permissionKeys = [];

        foreach ($definitions as $definition) {
            if (in_array($role->name, $definition->defaultRoles, true)) {
                $permissionKeys[] = $definition->key;
            }
        }

        if ($permissionKeys === []) {
            return;
        }

        $permissionIds = PermissionModel::whereIn('key', $permissionKeys)
            ->pluck('id')
            ->toArray();

        $role->permissions()->syncWithoutDetaching($permissionIds);
    }

    private function assignAllPermissions(RoleModel $role): void
    {
        $permissionIds = PermissionModel::pluck('id')->toArray();
        $role->permissions()->syncWithoutDetaching($permissionIds);
    }
}
