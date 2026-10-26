<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * permissions:sync runs on every deploy: it is how new core permissions reach existing sites.
 */
final class PermissionsSyncCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sync_creates_the_menu_item_permissions(): void
    {
        $this->artisanCommand('permissions:sync')->assertSuccessful();

        $this->assertSame(5, PermissionModel::query()->whereIn('key', [
            'menu_items.view_any',
            'menu_items.view',
            'menu_items.create',
            'menu_items.update',
            'menu_items.delete',
        ])->where('resource', 'menu_items')->count());
    }

    /**
     * The sync only creates and updates permissions: one no longer registered (a retired
     * permission, a module's after it is disabled or uninstalled) keeps its row and stays on the
     * roles that hold it. That is why it writes nothing to the role history; a sync that deleted
     * permissions would have to log what each role loses before the cascade.
     */
    public function test_sync_keeps_a_permission_no_longer_registered_on_the_roles_that_hold_it(): void
    {
        $retired = PermissionModel::query()->create([
            'key' => 'retired_items.view_any',
            'label' => 'Ver elementos retirados',
            'resource' => 'retired_items',
            'action' => 'view_any',
            'module' => 'retired-module',
        ]);
        $role = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta']);
        $role->permissions()->attach($retired->id);

        $this->artisanCommand('permissions:sync')->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['key' => 'retired_items.view_any']);
        $this->assertDatabaseHas('role_permission', ['role_id' => $role->id, 'permission_id' => $retired->id]);
        $this->assertDatabaseEmpty('role_audit_log');
    }
}
