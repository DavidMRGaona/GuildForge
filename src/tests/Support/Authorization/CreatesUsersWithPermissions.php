<?php

declare(strict_types=1);

namespace Tests\Support\Authorization;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

trait CreatesUsersWithPermissions
{
    /**
     * Panel access is granted by the editor role; an extra role carries the given permissions.
     *
     * @param  array<string>  $permissionKeys
     */
    protected function editorWithPermissions(array $permissionKeys): UserModel
    {
        $role = RoleModel::create([
            'name' => 'tester_'.uniqid(),
            'display_name' => 'Permissions tester',
        ]);

        foreach ($permissionKeys as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => $resource, 'action' => $action],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
