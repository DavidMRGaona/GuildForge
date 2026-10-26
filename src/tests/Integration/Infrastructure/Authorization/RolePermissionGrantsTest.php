<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Authorization;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\RolePermissionGrantsInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Module migrations grant permissions to roles through the core, so the role history shows
 * every grant: who, when and from where, like a change made in the role editor.
 */
final class RolePermissionGrantsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string JOIN = 'mymodule:games.join';

    private const string HOST = 'mymodule:games.host';

    private const string UPDATE = 'mymodule:games.update';

    private RolePermissionGrantsInterface $grants;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grants = app(RolePermissionGrantsInterface::class);
    }

    public function test_grant_to_role_creates_missing_permissions_and_logs_them(): void
    {
        $role = $this->role('member');

        $this->grants->grantToRole('member', $this->definition(self::JOIN, 'Unirse a partidas'));

        $permission = PermissionModel::query()->where('key', self::JOIN)->firstOrFail();
        $this->assertSame('Unirse a partidas', $permission->label);
        $this->assertSame('games', $permission->resource);
        $this->assertSame('join', $permission->action);
        $this->assertSame('mymodule', $permission->module);
        $this->assertSame([self::JOIN], $this->keysOf($role));

        $entries = $this->entriesFor($role);
        $this->assertCount(1, $entries);
        $this->assertSame('role_updated', $entries[0]->event);
        $this->assertNull($entries[0]->user_id);
        $this->assertSame(['permissions_added' => [self::JOIN]], $entries[0]->changes);
        // No request is being handled: a migration run from the console
        $this->assertSame('command', $entries[0]->source);
    }

    public function test_grant_keeps_existing_rows_and_permissions_and_logs_only_new_keys(): void
    {
        $role = $this->role('member');
        $held = $this->permission(self::JOIN, 'Label set by permissions:sync');
        $other = $this->permission('mymodule:games.view_any', 'View games');
        $role->permissions()->attach([$held->id, $other->id]);

        $this->grants->grantToRole(
            'member',
            $this->definition(self::JOIN, 'Fallback label'),
            $this->definition(self::HOST, 'Dirigir partidas'),
        );

        // The existing row keeps its label; nothing the role held is taken away
        $this->assertSame('Label set by permissions:sync', $held->fresh()?->label);
        $this->assertSame([self::HOST, self::JOIN, 'mymodule:games.view_any'], $this->keysOf($role));

        $entries = $this->entriesFor($role);
        $this->assertCount(1, $entries);
        $this->assertSame(['permissions_added' => [self::HOST]], $entries[0]->changes);
    }

    public function test_grant_already_held_writes_no_entry(): void
    {
        $role = $this->role('member');
        $role->permissions()->attach($this->permission(self::JOIN, 'Join')->id);

        $this->grants->grantToRole('member', $this->definition(self::JOIN, 'Join'));

        $this->assertSame([], $this->entriesFor($role));
    }

    public function test_grant_to_missing_role_does_nothing(): void
    {
        // Not "member": a core migration creates it
        $this->grants->grantToRole('treasurer', $this->definition(self::JOIN, 'Join'));

        $this->assertFalse(PermissionModel::query()->where('key', self::JOIN)->exists());
        $this->assertSame(0, RoleAuditEntryModel::query()->count());
    }

    public function test_grant_to_roles_with_reaches_every_holder_of_the_permission(): void
    {
        $update = $this->permission(self::UPDATE, 'Update games');
        $editor = $this->role('game-editor');
        $editor->permissions()->attach($update->id);
        $board = $this->role('board');
        $board->permissions()->attach($update->id);
        $plain = $this->role('helper');

        $this->grants->grantToRolesWith(
            self::UPDATE,
            $this->definition(self::HOST, 'Dirigir partidas'),
            $this->definition(self::JOIN, 'Unirse a partidas'),
        );

        foreach ([$editor, $board] as $role) {
            $this->assertSame([self::HOST, self::JOIN, self::UPDATE], $this->keysOf($role));
            $entries = $this->entriesFor($role);
            $this->assertCount(1, $entries);
            $this->assertSame(['permissions_added' => [self::HOST, self::JOIN]], $entries[0]->changes);
        }

        $this->assertSame([], $this->keysOf($plain));
        $this->assertSame([], $this->entriesFor($plain));
    }

    public function test_grant_to_roles_with_and_no_holder_does_nothing(): void
    {
        $this->role('helper');

        $this->grants->grantToRolesWith(self::UPDATE, $this->definition(self::HOST, 'Host'));

        $this->assertFalse(PermissionModel::query()->where('key', self::HOST)->exists());
        $this->assertSame(0, RoleAuditEntryModel::query()->count());
    }

    public function test_grant_records_the_actor(): void
    {
        $actor = UserModel::factory()->admin()->create();
        $this->actingAs($actor);
        $role = $this->role('member');

        $this->grants->grantToRole('member', $this->definition(self::JOIN, 'Join'));

        $this->assertSame($actor->id, $this->entriesFor($role)[0]->actor_id);
    }

    public function test_holders_get_the_permission_at_once(): void
    {
        $role = $this->role('member');
        $user = UserModel::factory()->create();
        $user->roles()->attach($role->id);
        $authorization = app(AuthorizationServiceInterface::class);
        // Cached without the permission
        $this->assertFalse($authorization->can($user, self::JOIN));

        $this->grants->grantToRole('member', $this->definition(self::JOIN, 'Join'));

        $this->assertTrue($authorization->can($user->fresh() ?? $user, self::JOIN));
    }

    public function test_rolled_back_grant_leaves_neither_permission_nor_entry(): void
    {
        $role = $this->role('member');

        try {
            DB::transaction(function (): void {
                $this->grants->grantToRole('member', $this->definition(self::JOIN, 'Join'));

                throw new RuntimeException('Migration failed after the grant');
            });
        } catch (Throwable) {
            // Expected: the migration's transaction is rolled back
        }

        $this->assertFalse(PermissionModel::query()->where('key', self::JOIN)->exists());
        $this->assertSame([], $this->keysOf($role));
        $this->assertSame([], $this->entriesFor($role));
    }

    private function role(string $name): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
    }

    private function permission(string $key, string $label): PermissionModel
    {
        [$module, $rest] = explode(':', $key, 2);
        [$resource, $action] = explode('.', $rest, 2);

        return PermissionModel::query()->create([
            'key' => $key,
            'label' => $label,
            'resource' => $resource,
            'action' => $action,
            'module' => $module,
        ]);
    }

    private function definition(string $key, string $label): PermissionDefinitionDTO
    {
        [$module, $rest] = explode(':', $key, 2);
        [$resource, $action] = explode('.', $rest, 2);

        return new PermissionDefinitionDTO(key: $key, label: $label, resource: $resource, action: $action, module: $module);
    }

    /**
     * @return list<string>
     */
    private function keysOf(RoleModel $role): array
    {
        /** @var list<string> $keys */
        $keys = $role->permissions()->orderBy('key')->pluck('key')->all();

        return $keys;
    }

    /**
     * @return list<RoleAuditEntryModel>
     */
    private function entriesFor(RoleModel $role): array
    {
        return array_values(RoleAuditEntryModel::query()->where('role_id', $role->id)->orderBy('id')->get()->all());
    }
}
