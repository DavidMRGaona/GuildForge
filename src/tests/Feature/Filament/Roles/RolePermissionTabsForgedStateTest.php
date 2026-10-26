<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Roles;

use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * A forged Livewire state can put ids under tab or section keys the form does not have, where no
 * checkbox list validates them. The server check before saving still decides on every id.
 */
final class RolePermissionTabsForgedStateTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    public function test_an_unknown_id_under_an_unknown_key_is_refused(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $role = $this->role(['events.view_any']);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.permissions_by_section.bogus.bogus', ['00000000-0000-4000-8000-000000000000'])
            ->call('save')
            ->assertNotified();

        $this->assertSame(['events.view_any'], $this->keys($role));
    }

    public function test_a_foreign_id_under_an_unknown_key_is_refused_for_a_non_admin(): void
    {
        $this->actingAs($this->editorWithPermissions(['admin.access', 'roles.view_any', 'roles.update', 'events.view_any']));
        $role = $this->role(['events.view_any']);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.permissions_by_section.bogus.bogus', [$this->id('users.delete')])
            ->call('save')
            ->assertNotified();

        $this->assertSame(['events.view_any'], $this->keys($role));
    }

    public function test_admin_access_hidden_in_a_section_of_the_base_role_is_refused(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $member = RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Miembro']);

        // Under a core section path, with the switch off as it is
        Livewire::test(EditRole::class, ['record' => $member->getRouteKey()])
            ->set('data.permissions_by_section.content.events', [$this->id('admin.access')])
            ->call('save')
            ->assertNotified();

        // Under a made-up key, with the switch off
        Livewire::test(EditRole::class, ['record' => $member->getRouteKey()])
            ->set('data.permissions_by_section.zzz.zzz', [$this->id('admin.access')])
            ->set('data.panel_access', false)
            ->call('save')
            ->assertNotified();

        $this->assertNotContains('admin.access', $this->keys($member));
    }

    public function test_nested_and_malformed_state_is_ignored(): void
    {
        $this->actingAs($this->editorWithPermissions(['admin.access', 'roles.view_any', 'roles.update', 'events.view_any']));
        $role = $this->role(['events.view_any']);

        // Ids nested deeper than a section, or a section that is not a list, are no ticks
        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.permissions_by_section.content.events', [])
            ->set('data.permissions_by_section.bogus', [[[$this->id('users.delete')]], 'x'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $this->keys($role));
    }

    /**
     * @param  list<string>  $keys
     */
    private function role(array $keys): RoleModel
    {
        $role = RoleModel::query()->create(['name' => 'role-'.uniqid(), 'display_name' => 'Role']);
        $role->permissions()->attach(array_map($this->id(...), $keys));

        return $role;
    }

    private function id(string $key): string
    {
        [$resource, $action] = explode('.', $key, 2);

        return (string) PermissionModel::query()->firstOrCreate(
            ['key' => $key],
            ['label' => $key, 'resource' => $resource, 'action' => $action],
        )->id;
    }

    /**
     * @return list<string>
     */
    private function keys(RoleModel $role): array
    {
        $keys = array_values(array_map(strval(...), $role->permissions()->pluck('permissions.key')->all()));
        sort($keys);

        return $keys;
    }
}
