<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Authorization\DTOs\CreateRoleDTO;
use App\Application\Authorization\DTOs\UpdateRoleDTO;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Livewire\LivewireManager;
use Tests\TestCase;
use Throwable;

/**
 * Every role assignment and removal, and every change to a role's definition, leaves an entry
 * in the role history: who, when and from where. The entry is written with the change: when
 * it cannot be written, the change is not applied.
 */
final class RoleAuditLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthorizationServiceInterface $authorization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorization = app(AuthorizationServiceInterface::class);

        RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Miembro']);
    }

    public function test_assign_and_remove_are_logged_with_actor_and_source(): void
    {
        $actor = UserModel::factory()->admin()->create();
        $this->actingAs($actor);
        $role = $this->role('game-master');
        $target = UserModel::factory()->create();

        $this->authorization->assignRole($target, 'game-master');
        // Already held: no new assignment, no new entry
        $this->authorization->assignRole($target, 'game-master');

        $assigned = $this->entriesFor($target);
        $this->assertCount(1, $assigned);
        $this->assertSame('assigned', $assigned[0]->event);
        $this->assertSame($role->id, $assigned[0]->role_id);
        $this->assertSame('game-master', $assigned[0]->role_name);
        $this->assertSame($actor->id, $assigned[0]->actor_id);
        // No request is being handled: it ran from the console
        $this->assertSame('command', $assigned[0]->source);
        $this->assertEqualsWithDelta(now()->getTimestamp(), $assigned[0]->created_at->getTimestamp(), 5);

        $this->authorization->removeRole($target, 'game-master');
        // Not held any more, and the base role is never taken away: no entries
        $this->authorization->removeRole($target, 'game-master');
        $this->authorization->removeRole($target, 'member');

        $entries = $this->entriesFor($target);
        $this->assertSame(['assigned', 'removed'], array_map(static fn (RoleAuditEntryModel $entry): string => $entry->event, $entries));
        $this->assertSame($actor->id, $entries[1]->actor_id);
    }

    public function test_sync_logs_only_real_differences(): void
    {
        $this->role('game-master');
        $this->role('librarian');
        $this->role('treasurer');
        $target = UserModel::factory()->create();
        $target->roles()->attach(RoleModel::query()->whereIn('name', ['member', 'game-master', 'librarian'])->pluck('id')->all());

        $this->authorization->syncRoles($target, ['librarian', 'treasurer']);
        // The same set again: nothing changes, nothing is logged
        $this->authorization->syncRoles($target, ['member', 'librarian', 'treasurer']);

        $entries = $this->entriesFor($target);
        $this->assertEqualsCanonicalizing(
            ['removed:game-master', 'assigned:treasurer'],
            array_map(static fn (RoleAuditEntryModel $entry): string => $entry->event.':'.$entry->role_name, $entries),
        );
        // The actor is null when nobody is signed in
        $this->assertNull($entries[0]->actor_id);
    }

    public function test_form_role_change_is_logged(): void
    {
        $actor = UserModel::factory()->admin()->create();
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->role('game-master');
        $target = UserModel::factory()->create();
        app(MemberRoleServiceInterface::class)->assignTo($target);
        RoleAuditEntryModel::query()->delete();

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => ['member', 'game-master']])
            ->call('save')
            ->assertHasNoFormErrors();

        $entries = $this->entriesFor($target);
        // Logged once by the service, not again by the form
        $this->assertCount(1, $entries);
        $this->assertSame('assigned', $entries[0]->event);
        $this->assertSame('game-master', $entries[0]->role_name);
        $this->assertSame($actor->id, $entries[0]->actor_id);
        $this->assertSame('panel', $entries[0]->source);
    }

    public function test_role_permission_change_logs_added_and_removed_keys(): void
    {
        $actor = UserModel::factory()->admin()->create();
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $role = $this->role('game-master');
        $role->permissions()->attach([$this->permission('events.view_any')->id, $this->permission('events.create')->id]);
        $update = $this->permission('events.update');

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.permissions', [$this->permission('events.view_any')->id, $update->id])
            ->set('data.max_holders', 2)
            ->set('data.name', 'game-master-lead')
            ->call('save')
            ->assertHasNoFormErrors();

        $entry = $this->definitionEntries($role->id)[0];
        $this->assertSame('role_updated', $entry->event);
        $this->assertNull($entry->user_id);
        $this->assertSame('game-master-lead', $entry->role_name);
        $this->assertSame($actor->id, $entry->actor_id);
        $this->assertSame('panel', $entry->source);
        $this->assertSame(['events.update'], $entry->changes['permissions_added'] ?? null);
        $this->assertSame(['events.create'], $entry->changes['permissions_removed'] ?? null);
        $this->assertSame([
            'name' => ['from' => 'game-master', 'to' => 'game-master-lead'],
            'max_holders' => ['from' => null, 'to' => 2],
        ], $entry->changes['fields'] ?? null);

        // Saving again without changes leaves no entry
        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertCount(1, $this->definitionEntries($role->id));

        // A new role from the panel
        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'librarian',
                'display_name' => 'Biblioteca',
                'permissions' => [$update->id],
                'requires_association_member' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = RoleModel::query()->where('name', 'librarian')->firstOrFail();
        $createdEntry = $this->definitionEntries($created->id)[0];
        $this->assertSame('role_created', $createdEntry->event);
        $this->assertSame(['events.update'], $createdEntry->changes['permissions_added'] ?? null);
        $this->assertSame(['from' => null, 'to' => true], $createdEntry->changes['fields']['requires_association_member'] ?? null);

        // And through the role service
        $roles = app(RoleServiceInterface::class);
        $roles->syncPermissions($created->id, ['events.update', 'events.create']);
        $roles->assignPermissions($created->id, ['events.view_any', 'events.create']);
        $roles->update($created->id, new UpdateRoleDTO(displayName: 'Biblioteca y archivo', permissionKeys: ['events.view_any']));
        // Only the display name: not a change the history keeps
        $roles->update($created->id, new UpdateRoleDTO(displayName: 'Archivo'));
        $byService = $roles->create(new CreateRoleDTO(name: 'archivist', displayName: 'Archivo', permissionKeys: ['events.create']));

        $changes = array_map(
            static fn (RoleAuditEntryModel $entry): array => [$entry->event, $entry->changes['permissions_added'] ?? [], $entry->changes['permissions_removed'] ?? []],
            array_slice($this->definitionEntries($created->id), 1),
        );
        $this->assertSame([
            ['role_updated', ['events.create'], []],
            ['role_updated', ['events.view_any'], []],
            ['role_updated', [], ['events.create', 'events.update']],
        ], $changes);
        $this->assertSame('role_created', $this->definitionEntries($byService->id)[0]->event);
        $this->assertSame(['events.create'], $this->definitionEntries($byService->id)[0]->changes['permissions_added'] ?? null);
    }

    public function test_bulk_member_assignment_is_logged(): void
    {
        $withMember = UserModel::factory()->create();
        app(MemberRoleServiceInterface::class)->assignTo($withMember);
        $without = UserModel::factory()->count(3)->create();
        RoleAuditEntryModel::query()->delete();

        $assigned = app(MemberRoleServiceInterface::class)->assignToAllUsers(chunkSize: 2);

        $this->assertSame(3, $assigned);
        $entries = RoleAuditEntryModel::query()->get();
        $this->assertCount(3, $entries);
        $this->assertEqualsCanonicalizing($without->pluck('id')->all(), $entries->pluck('user_id')->all());
        $this->assertTrue($entries->every(static fn (RoleAuditEntryModel $entry): bool => $entry->event === 'assigned'
            && $entry->role_name === 'member'
            && $entry->source === 'command'));
        $this->assertSame([], $this->entriesFor($withMember));
    }

    public function test_anonymizing_logs_each_removed_role(): void
    {
        $actor = UserModel::factory()->admin()->create();
        $this->actingAs($actor);
        $this->role('game-master');
        $target = UserModel::factory()->create();
        $target->roles()->attach(RoleModel::query()->whereIn('name', ['member', 'game-master'])->pluck('id')->all());
        $target->delete();

        app(UserServiceInterface::class)->anonymize($target->id);

        $entries = $this->entriesFor($target);
        $this->assertEqualsCanonicalizing(
            ['removed:member', 'removed:game-master'],
            array_map(static fn (RoleAuditEntryModel $entry): string => $entry->event.':'.$entry->role_name, $entries),
        );
        $this->assertSame([$actor->id], array_values(array_unique(array_map(static fn (RoleAuditEntryModel $entry): ?string => $entry->actor_id, $entries))));
        $this->assertSame([], $target->roles()->pluck('roles.name')->all());
    }

    public function test_entries_survive_role_deletion(): void
    {
        $actor = UserModel::factory()->admin()->create();
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $byService = $this->role('game-master');
        $fromPanel = $this->role('librarian');
        $target = UserModel::factory()->create();
        $this->authorization->assignRole($target, 'game-master');
        $this->authorization->assignRole($target, 'librarian');

        app(RoleServiceInterface::class)->delete($byService->id);
        Livewire::test(ListRoles::class)
            ->callTableAction(DeleteAction::class, $fromPanel);

        $this->assertNull(RoleModel::query()->find($byService->id));
        $this->assertNull(RoleModel::query()->find($fromPanel->id));

        // The holder lost both roles: one entry each, kept with the role's name
        $this->assertEqualsCanonicalizing(
            ['assigned:game-master', 'assigned:librarian', 'removed:game-master', 'removed:librarian'],
            array_map(static fn (RoleAuditEntryModel $entry): string => $entry->event.':'.$entry->role_name, $this->entriesFor($target)),
        );

        foreach ([$byService, $fromPanel] as $role) {
            $deleted = $this->definitionEntries($role->id);
            $this->assertCount(1, $deleted);
            $this->assertSame('role_deleted', $deleted[0]->event);
            $this->assertSame($role->name, $deleted[0]->role_name);
            $this->assertSame($actor->id, $deleted[0]->actor_id);
        }

        $this->assertSame('command', $this->definitionEntries($byService->id)[0]->source);
        $this->assertSame('panel', $this->definitionEntries($fromPanel->id)[0]->source);
    }

    public function test_change_is_not_applied_when_the_entry_cannot_be_written(): void
    {
        $this->role('game-master');
        $target = UserModel::factory()->create();
        Schema::drop('role_audit_log');

        try {
            $this->authorization->assignRole($target, 'game-master');
            $this->fail('A change that cannot be logged must not be applied.');
        } catch (Throwable) {
            // The history table is gone
        }

        $this->assertSame([], $target->roles()->pluck('roles.name')->all());
    }

    public function test_role_edit_is_rolled_back_when_its_entry_cannot_be_written(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = $this->role('game-master');
        $role->permissions()->attach($this->permission('events.view_any')->id);
        $added = $this->permission('events.create');
        Schema::drop('role_audit_log');

        try {
            Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
                ->set('data.permissions', [$this->permission('events.view_any')->id, $added->id])
                ->set('data.max_holders', 3)
                ->call('save');
            $this->fail('A role change that cannot be logged must not be saved.');
        } catch (Throwable) {
            // The history table is gone
        }

        $this->assertSame(['events.view_any'], $role->permissions()->pluck('permissions.key')->all());
        $this->assertNull($role->fresh()?->max_holders);
    }

    public function test_source_comes_from_where_the_change_is_made(): void
    {
        $this->role('game-master');
        $target = UserModel::factory()->create();

        // A queued job
        dispatch(function () use ($target): void {
            app(AuthorizationServiceInterface::class)->assignRole($target, 'game-master');
        });
        $this->assertSame('queue', $this->entriesFor($target)[0]->source);

        // A panel page and a public page
        $this->handling(Request::create('/admin/users/1/edit', 'POST'));
        $this->authorization->removeRole($target, 'game-master');
        $this->handling(Request::create('/eventos', 'POST'));
        $this->authorization->assignRole($target, 'game-master');

        // A Livewire update made from a panel page, and one made from a public page
        $this->handling($this->livewireUpdateFrom('admin/users/1/edit'));
        $this->authorization->removeRole($target, 'game-master');
        $this->handling($this->livewireUpdateFrom('eventos'));
        $this->authorization->assignRole($target, 'game-master');

        $this->assertSame(
            ['queue', 'panel', 'web', 'panel', 'web'],
            array_map(static fn (RoleAuditEntryModel $entry): string => $entry->source, $this->entriesFor($target)),
        );
    }

    private function handling(Request $request): void
    {
        $request->setRouteResolver(static fn (): Route => new Route($request->method(), $request->path(), static fn (): null => null));
        $this->app->instance('request', $request);
    }

    private function livewireUpdateFrom(string $path): Request
    {
        $prefix = trim(app(LivewireManager::class)->getUriPrefix(), '/');

        return Request::create('/'.$prefix.'/update', 'POST', [
            'components' => [['snapshot' => json_encode(['memo' => ['path' => $path]], JSON_THROW_ON_ERROR)]],
        ]);
    }

    private function role(string $name): RoleModel
    {
        return RoleModel::query()->create(['name' => $name, 'display_name' => ucfirst($name)]);
    }

    private function permission(string $key): PermissionModel
    {
        [$resource, $action] = explode('.', $key, 2);

        return PermissionModel::query()->firstOrCreate(
            ['key' => $key],
            ['label' => $key, 'resource' => $resource, 'action' => $action],
        );
    }

    /**
     * @return list<RoleAuditEntryModel>
     */
    private function entriesFor(UserModel $user): array
    {
        return array_values(RoleAuditEntryModel::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->all());
    }

    /**
     * @return list<RoleAuditEntryModel>
     */
    private function definitionEntries(string $roleId): array
    {
        return array_values(RoleAuditEntryModel::query()
            ->where('role_id', $roleId)
            ->whereNull('user_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->all());
    }
}
