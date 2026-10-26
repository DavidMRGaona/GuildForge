<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Roles;

use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Domain\Authorization\Enums\RoleAuditSource;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\RelationManagers\RoleAuditRelationManager as RoleHistory;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\RelationManagers\RoleAuditRelationManager as UserHistory;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class RoleAuditPanelTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = UserModel::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_user_page_lists_role_history_newest_first(): void
    {
        $user = UserModel::factory()->create();
        $older = $this->entry(['user_id' => $user->id, 'role_name' => 'older-role', 'created_at' => now()->subDays(2)]);
        $newer = $this->entry(['user_id' => $user->id, 'role_name' => 'newer-role', 'created_at' => now()->subDay()]);
        $this->entry(['user_id' => UserModel::factory()->create()->id, 'role_name' => 'someone-else']);

        Livewire::test(UserHistory::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->assertCountTableRecords(2)
            ->assertSee('Rol asignado');
    }

    public function test_history_shows_the_display_name_of_an_existing_role_and_the_stored_name_of_a_deleted_one(): void
    {
        $role = RoleModel::create(['name' => 'tesoreria', 'display_name' => 'Tesorería del club']);
        $alive = $this->entry(['role_id' => $role->id, 'role_name' => 'tesoreria']);
        $gone = $this->entry(['role_id' => null, 'role_name' => 'borrado']);
        $user = UserModel::factory()->create();
        $alive->forceFill(['user_id' => $user->id])->save();
        $gone->forceFill(['user_id' => $user->id])->save();

        Livewire::test(UserHistory::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
            ->assertSee('Tesorería del club')
            ->assertSee('borrado');
    }

    public function test_role_page_lists_its_history(): void
    {
        $role = RoleModel::create(['name' => 'history-role', 'display_name' => 'History role']);
        $mine = $this->entry([
            'role_id' => $role->id,
            'role_name' => 'history-role',
            'event' => RoleAuditEvent::RoleUpdated->value,
            'changes' => ['fields' => ['requires_association_member' => ['from' => false, 'to' => true]]],
        ]);
        $other = $this->entry(['role_id' => null, 'role_name' => 'other-role']);

        Livewire::test(RoleHistory::class, ['ownerRecord' => $role, 'pageClass' => EditRole::class])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other])
            ->assertSee('Requiere ser socio: no → sí');
    }

    public function test_history_is_read_only(): void
    {
        $user = UserModel::factory()->create();
        $this->entry(['user_id' => $user->id]);

        $component = Livewire::test(UserHistory::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class]);
        $manager = $component->instance();
        $this->assertInstanceOf(UserHistory::class, $manager);
        $table = $manager->getTable();

        $this->assertTrue($manager->isReadOnly());
        $this->assertSame([], $table->getHeaderActions());
        $this->assertSame([], $table->getFlatRecordActions());
        $this->assertSame([], $table->getToolbarActions());
    }

    public function test_it_is_only_shown_to_those_who_can_view_the_record(): void
    {
        $user = UserModel::factory()->create();
        $role = RoleModel::create(['name' => 'visible-role', 'display_name' => 'Visible']);

        $this->actingAs($this->editorWithPermissions([]));
        $this->assertFalse(UserHistory::canViewForRecord($user, EditUser::class));
        $this->assertFalse(RoleHistory::canViewForRecord($role, EditRole::class));

        $this->actingAs($this->editorWithPermissions(['users.view', 'roles.view']));
        $this->assertTrue(UserHistory::canViewForRecord($user, EditUser::class));
        $this->assertTrue(RoleHistory::canViewForRecord($role, EditRole::class));
    }

    public function test_anonymized_actor_is_shown_as_anonymized_account(): void
    {
        $user = UserModel::factory()->create();
        $anonymized = UserModel::factory()->create(['anonymized_at' => now()]);
        $named = UserModel::factory()->create(['name' => 'Jane Roe', 'display_name' => null]);
        $this->entry(['user_id' => $user->id, 'actor_id' => $anonymized->id, 'created_at' => now()->subDays(3)]);
        $this->entry(['user_id' => $user->id, 'actor_id' => $named->id, 'created_at' => now()->subDays(2)]);
        $system = $this->entry(['user_id' => $user->id, 'actor_id' => null, 'source' => RoleAuditSource::Command->value, 'created_at' => now()->subDay()]);

        Livewire::test(UserHistory::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
            ->assertTableColumnStateSet('actor_id', 'Sistema', $system)
            ->assertSee('Cuenta anonimizada')
            ->assertSee('Jane Roe')
            ->assertSee('Comando');
    }

    public function test_entries_of_the_same_second_keep_a_stable_newest_first_order(): void
    {
        $user = UserModel::factory()->create();
        $at = now()->subDay()->startOfSecond();
        // Explicit ids, ordered as UUIDv7 would be: within one millisecond their order is not guaranteed
        $first = $this->entry(['id' => '019a0000-0000-7000-8000-000000000001', 'user_id' => $user->id, 'created_at' => $at]);
        $third = $this->entry(['id' => '019a0000-0000-7000-8000-000000000003', 'user_id' => $user->id, 'created_at' => $at]);
        $second = $this->entry(['id' => '019a0000-0000-7000-8000-000000000002', 'user_id' => $user->id, 'created_at' => $at]);

        Livewire::test(UserHistory::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
            ->assertCanSeeTableRecords([$third, $second, $first], inOrder: true);
    }

    public function test_date_is_shown_in_the_display_zone(): void
    {
        config(['app.display_timezone' => 'Asia/Tokyo']);
        $user = UserModel::factory()->create();
        $storage = app(TimezoneServiceInterface::class)->storageTimezone();
        $instant = new DateTimeImmutable('2026-03-10 12:00:00', $storage);
        $this->entry(['user_id' => $user->id, 'created_at' => $instant]);
        $expected = $instant->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('d/m/Y H:i');

        Livewire::test(UserHistory::class, ['ownerRecord' => $user, 'pageClass' => EditUser::class])
            ->assertSee($expected);
    }

    public function test_role_history_hides_account_names_from_those_who_cannot_see_users(): void
    {
        $role = RoleModel::create(['name' => 'hidden-role', 'display_name' => 'Hidden']);
        $holder = UserModel::factory()->create(['name' => 'Holder Person', 'display_name' => null]);
        $actor = UserModel::factory()->create(['name' => 'Actor Person', 'display_name' => null]);
        $this->entry(['user_id' => $holder->id, 'role_id' => $role->id, 'actor_id' => $actor->id]);

        $this->actingAs($this->editorWithPermissions(['roles.view']));
        Livewire::test(RoleHistory::class, ['ownerRecord' => $role, 'pageClass' => EditRole::class])
            ->assertSee('Oculto')
            ->assertDontSee('Holder Person')
            ->assertDontSee('Actor Person');

        $this->actingAs($this->editorWithPermissions(['roles.view', 'users.view_any']));
        Livewire::test(RoleHistory::class, ['ownerRecord' => $role, 'pageClass' => EditRole::class])
            ->assertSee('Holder Person')
            ->assertSee('Actor Person');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entry(array $attributes = []): RoleAuditEntryModel
    {
        $entry = new RoleAuditEntryModel;
        $entry->forceFill(array_merge([
            'user_id' => null,
            'role_id' => null,
            'role_name' => 'some-role',
            'event' => RoleAuditEvent::Assigned->value,
            'changes' => null,
            'actor_id' => null,
            'source' => RoleAuditSource::Panel->value,
            'created_at' => now(),
        ], $attributes))->save();

        return $entry;
    }
}
