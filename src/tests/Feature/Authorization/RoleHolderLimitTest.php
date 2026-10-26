<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Authorization\Exceptions\RoleHolderLimitReachedException;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\RestoreAction;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A role with a holder limit (max_holders) takes no new holder once it is full. Active
 * accounts count, also those whose role is inactive because they are not association
 * members; deactivated and anonymized accounts do not. Restoring an account goes ahead, but
 * drops the roles that are full meanwhile and says so.
 */
final class RoleHolderLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    private AuthorizationServiceInterface $authorization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorization = app(AuthorizationServiceInterface::class);

        RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Miembro']);
        RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
    }

    public function test_assigning_a_full_role_names_the_current_holder(): void
    {
        $this->cappedRole('treasurer', 'Tesorería', 1);
        $holder = $this->person('Ana López', ['treasurer']);
        $other = $this->person('Bea Ruiz');

        try {
            $this->authorization->assignRole($other, 'treasurer');
            $this->fail('A full role must not take another holder.');
        } catch (RoleHolderLimitReachedException $e) {
            $this->assertSame(
                'Tesorería ya la tiene Ana López. Quítasela antes de dársela a otra persona.',
                $e->getMessage(),
            );
        }

        $this->assertFalse($this->holds($other, 'treasurer'));
        $this->assertTrue($this->holds($holder, 'treasurer'));

        // Assigning it again to whoever already holds it is no new holder
        $this->authorization->assignRole($holder, 'treasurer');
        $this->assertTrue($this->holds($holder, 'treasurer'));
    }

    public function test_the_holder_with_an_inactive_role_still_counts(): void
    {
        $this->cappedRole('treasurer', 'Tesorería', 1, requiresMembership: true);
        $holder = $this->person('Ana López', ['treasurer']);
        $this->assertFalse($this->authorization->hasRole($holder, 'treasurer'), 'The role is inactive for a non-member.');

        $this->expectException(RoleHolderLimitReachedException::class);
        $this->expectExceptionMessage('Tesorería ya la tiene Ana López.');

        $this->authorization->assignRole($this->person('Bea Ruiz', ['socio']), 'treasurer');
    }

    public function test_deactivated_and_anonymized_accounts_do_not_count(): void
    {
        $this->cappedRole('treasurer', 'Tesorería', 1);
        $deactivated = $this->person('Ana López', ['treasurer']);
        $deactivated->delete();
        $anonymized = $this->person('Carlos Gil', ['treasurer']);
        $anonymized->forceFill(['anonymized_at' => now()])->save();
        $this->assertFalse($anonymized->trashed());

        $newHolder = $this->person('Bea Ruiz');
        $this->authorization->assignRole($newHolder, 'treasurer');

        $this->assertTrue($this->holds($newHolder, 'treasurer'));
    }

    public function test_handover_works_after_removing_the_holder(): void
    {
        $this->cappedRole('treasurer', 'Tesorería', 1);
        $holder = $this->person('Ana López', ['treasurer']);
        $next = $this->person('Bea Ruiz');

        $this->authorization->removeRole($holder, 'treasurer');
        $this->authorization->assignRole($next, 'treasurer');

        $this->assertFalse($this->holds($holder, 'treasurer'));
        $this->assertTrue($this->holds($next, 'treasurer'));
    }

    public function test_sync_roles_respects_the_limit_and_writes_nothing_on_failure(): void
    {
        $this->cappedRole('treasurer', 'Tesorería', 1);
        RoleModel::query()->create(['name' => 'secretary', 'display_name' => 'Secretaría']);
        RoleModel::query()->create(['name' => 'organizer', 'display_name' => 'Organización']);
        $holder = $this->person('Ana López', ['member', 'treasurer']);
        $other = $this->person('Bea Ruiz', ['member', 'organizer']);

        try {
            $this->authorization->syncRoles($other, ['secretary', 'treasurer']);
            $this->fail('A full role must not take another holder through syncRoles.');
        } catch (RoleHolderLimitReachedException $e) {
            $this->assertStringContainsString('Ana López', $e->getMessage());
        }

        $this->assertEqualsCanonicalizing(['member', 'organizer'], $this->roleNames($other));

        // A role the user already holds is not new: syncing it again is no check
        $this->authorization->syncRoles($holder, ['treasurer', 'secretary']);
        $this->assertEqualsCanonicalizing(['member', 'treasurer', 'secretary'], $this->roleNames($holder));
    }

    public function test_restoring_an_account_drops_a_full_role_and_says_so(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->cappedRole('treasurer', 'Tesorería', 1);
        RoleModel::query()->create(['name' => 'organizer', 'display_name' => 'Organización']);
        $former = $this->person('Ana López', ['member', 'treasurer', 'organizer']);
        $former->delete();
        $this->authorization->assignRole($this->person('Bea Ruiz'), 'treasurer');

        Livewire::test(EditUser::class, ['record' => $former->getRouteKey()])
            ->callAction(RestoreAction::class)
            ->assertNotified(__('filament.users.actions.restoredWithoutRoles', ['roles' => 'Tesorería']));

        $former->refresh();
        $this->assertFalse($former->trashed());
        $this->assertEqualsCanonicalizing(['member', 'organizer'], $this->roleNames($former));
        $this->assertFalse($this->authorization->hasRole($former, 'treasurer'));
    }

    public function test_restoring_from_the_table_drops_a_full_role_too(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->cappedRole('treasurer', 'Tesorería', 1);
        $former = $this->person('Ana López', ['member', 'treasurer']);
        $former->delete();
        $this->authorization->assignRole($this->person('Bea Ruiz'), 'treasurer');

        Livewire::test(ListUsers::class)
            ->callTableAction(RestoreAction::class, $former)
            ->assertNotified(__('filament.users.actions.restoredWithoutRoles', ['roles' => 'Tesorería']));

        $former->refresh();
        $this->assertFalse($former->trashed());
        $this->assertSame(['member'], $this->roleNames($former));
    }

    public function test_restoring_keeps_roles_that_still_have_room(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->cappedRole('treasurer', 'Tesorería', 2);
        $former = $this->person('Ana López', ['member', 'treasurer']);
        $former->delete();
        $this->authorization->assignRole($this->person('Bea Ruiz'), 'treasurer');

        Livewire::test(EditUser::class, ['record' => $former->getRouteKey()])
            ->callAction(RestoreAction::class)
            ->assertNotNotified(__('filament.users.actions.restoredWithoutRoles', ['roles' => 'Tesorería']));

        $this->assertEqualsCanonicalizing(['member', 'treasurer'], $this->roleNames($former->refresh()));
    }

    public function test_lowering_the_limit_removes_nobody(): void
    {
        $role = $this->cappedRole('treasurer', 'Tesorería', 2);
        $first = $this->person('Ana López', ['treasurer']);
        $second = $this->person('Bea Ruiz', ['treasurer']);

        $role->update(['max_holders' => 1]);

        $this->assertTrue($this->holds($first, 'treasurer'));
        $this->assertTrue($this->holds($second, 'treasurer'));

        $this->expectException(RoleHolderLimitReachedException::class);
        $this->expectExceptionMessage('Tesorería ya la tiene Ana López, Bea Ruiz.');

        $this->authorization->assignRole($this->person('Carlos Gil'), 'treasurer');
    }

    public function test_admin_cannot_bypass_the_limit_either(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->cappedRole('treasurer', 'Tesorería', 1);
        $this->person('Ana López', ['treasurer']);
        $admin = UserModel::factory()->admin()->create();

        $this->expectException(RoleHolderLimitReachedException::class);

        $this->authorization->assignRole($admin, 'treasurer');
    }

    public function test_the_check_locks_the_role_row(): void
    {
        $this->cappedRole('treasurer', 'Tesorería', 1);
        $user = $this->person('Bea Ruiz');
        $connection = DB::connection();

        // SQLite has no row locks and compiles them to nothing: make the lock visible there
        if ($connection->getDriverName() === 'sqlite') {
            $connection->setQueryGrammar(new class($connection) extends SQLiteGrammar
            {
                protected function compileLock(Builder $query, $value): string
                {
                    return $value === true ? ' /* for update */' : '';
                }
            });
        }

        $locked = [];
        DB::listen(function (QueryExecuted $query) use (&$locked): void {
            if (str_contains(strtolower($query->sql), 'for update') && str_contains($query->sql, 'roles')) {
                $locked[] = $query->sql;
            }
        });

        $this->authorization->assignRole($user, 'treasurer');

        $this->assertNotEmpty($locked, 'The role row must be read with lockForUpdate.');
    }

    private function cappedRole(string $name, string $displayName, int $maxHolders, bool $requiresMembership = false): RoleModel
    {
        return RoleModel::query()->create([
            'name' => $name,
            'display_name' => $displayName,
            'max_holders' => $maxHolders,
            'requires_association_member' => $requiresMembership,
        ]);
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function person(string $name, array $roleNames = []): UserModel
    {
        $user = UserModel::factory()->create(['name' => $name, 'display_name' => null]);
        $ids = RoleModel::query()->whereIn('name', $roleNames)->pluck('id')->all();
        $user->roles()->attach($ids);

        return $user;
    }

    private function holds(UserModel $user, string $roleName): bool
    {
        return $user->roles()->where('roles.name', $roleName)->exists();
    }

    /**
     * @return list<string>
     */
    private function roleNames(UserModel $user): array
    {
        /** @var list<string> */
        return $user->roles()->pluck('roles.name')->all();
    }
}
