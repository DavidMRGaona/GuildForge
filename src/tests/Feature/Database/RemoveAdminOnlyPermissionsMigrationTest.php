<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Database\RunsMigrationFiles;
use Tests\TestCase;

/**
 * Roles other than the administrator lose the admin-only permissions they were given before the
 * rule existed, through the role service, so the role history shows what each one lost.
 */
final class RemoveAdminOnlyPermissionsMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RunsMigrationFiles;

    public function test_it_removes_them_from_every_role_but_admin_and_logs_it(): void
    {
        $admin = $this->roleWith('admin', ['modules.manage', 'updates.apply']);
        $board = $this->roleWith('junta', ['modules.manage', 'updates.apply', 'events.create']);
        $editor = $this->roleWith('editor', ['events.create']);

        $this->runMigrationFile($this->migration());

        $this->assertEqualsCanonicalizing(['modules.manage', 'updates.apply'], $this->keys($admin));
        $this->assertSame(['events.create'], $this->keys($board));
        $this->assertSame(['events.create'], $this->keys($editor));

        $this->assertSame(1, RoleAuditEntryModel::query()->where('role_id', $board->id)->count());
        $entry = RoleAuditEntryModel::query()->where('role_id', $board->id)->firstOrFail();
        $this->assertSame('role_updated', $entry->event);
        $this->assertEqualsCanonicalizing(['modules.manage', 'updates.apply'], $entry->changes['permissions_removed'] ?? null);
        $this->assertSame(0, RoleAuditEntryModel::query()->whereIn('role_id', [$admin->id, $editor->id])->count());

        // Permission rows are never deleted
        $this->assertTrue(PermissionModel::query()->where('key', 'modules.manage')->exists());
    }

    public function test_it_does_nothing_when_no_other_role_holds_them(): void
    {
        $this->roleWith('admin', ['modules.manage']);

        $this->runMigrationFile($this->migration());

        $this->assertSame(0, RoleAuditEntryModel::query()->count());
    }

    /**
     * @param  list<string>  $keys
     */
    private function roleWith(string $name, array $keys): RoleModel
    {
        $role = RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => $name]);

        foreach ($keys as $key) {
            [$resource, $action] = explode('.', $key, 2);
            $permission = PermissionModel::query()->firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => $resource, 'action' => $action],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $role;
    }

    /**
     * @return list<string>
     */
    private function keys(RoleModel $role): array
    {
        return array_values(array_map(strval(...), $role->permissions()->pluck('permissions.key')->all()));
    }

    private function migration(): string
    {
        return database_path('migrations/2026_11_09_100001_remove_admin_only_permissions_from_other_roles.php');
    }
}
