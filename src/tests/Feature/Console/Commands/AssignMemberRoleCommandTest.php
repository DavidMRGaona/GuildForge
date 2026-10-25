<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Accounts created before every new account got "member" are given it once: all of them,
 * including admins and association members, without ever removing a role.
 */
final class AssignMemberRoleCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private RoleModel $member;

    private UserModel $adminAndSocio;

    private UserModel $withoutRoles;

    private UserModel $alreadyMember;

    protected function setUp(): void
    {
        parent::setUp();

        // Created by a migration; firstOrCreate keeps the test independent of that
        $this->member = RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        $admin = RoleModel::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator', 'is_protected' => true]);
        $socio = RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);

        $this->adminAndSocio = UserModel::factory()->create();
        $this->adminAndSocio->roles()->attach([$admin->id, $socio->id]);
        $this->withoutRoles = UserModel::factory()->create();
        $this->alreadyMember = UserModel::factory()->create();
        $this->alreadyMember->roles()->attach($this->member->id);
    }

    public function test_it_gives_member_to_every_user_without_it_and_keeps_their_roles(): void
    {
        $alreadyMemberSince = $this->memberSince($this->alreadyMember);
        $this->assertNotNull($alreadyMemberSince);

        $this->artisanCommand('users:assign-member-role')
            ->expectsOutputToContain('2 usuarios actualizados')
            ->assertSuccessful();

        $this->assertSame(['admin', 'member', 'socio'], $this->roleNames($this->adminAndSocio));
        $this->assertSame(['member'], $this->roleNames($this->withoutRoles));
        $this->assertSame(['member'], $this->roleNames($this->alreadyMember));
        $this->assertSame($alreadyMemberSince, $this->memberSince($this->alreadyMember));
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->artisanCommand('users:assign-member-role')->assertSuccessful();

        $this->artisanCommand('users:assign-member-role')
            ->expectsOutputToContain('0 usuarios actualizados')
            ->assertSuccessful();

        $this->assertSame(3, $this->member->users()->count());
    }

    public function test_a_dry_run_counts_without_writing(): void
    {
        $this->artisanCommand('users:assign-member-role', ['--dry-run' => true])
            ->expectsOutputToContain('2 usuarios se actualizarían')
            ->assertSuccessful();

        $this->assertSame(1, $this->member->users()->count());
    }

    public function test_it_fails_without_the_member_role(): void
    {
        $this->member->delete();

        $this->artisanCommand('users:assign-member-role')
            ->expectsOutputToContain('No existe el rol «member»')
            ->assertFailed();

        $this->assertSame(['admin', 'socio'], $this->roleNames($this->adminAndSocio));
    }

    public function test_the_new_role_applies_at_once_despite_the_authorization_cache(): void
    {
        $authorization = app(AuthorizationServiceInterface::class);
        $this->assertFalse($authorization->hasRole($this->withoutRoles, 'member'));

        $this->artisanCommand('users:assign-member-role')->assertSuccessful();

        $this->assertTrue($authorization->hasRole($this->withoutRoles, 'member'));
        $this->assertTrue($authorization->hasRole($this->adminAndSocio, 'member'));
    }

    public function test_soft_deleted_users_get_it_as_well(): void
    {
        $deleted = UserModel::factory()->create();
        $deleted->delete();

        $this->artisanCommand('users:assign-member-role')
            ->expectsOutputToContain('3 usuarios actualizados')
            ->assertSuccessful();

        $this->assertTrue($deleted->roles()->where('name', 'member')->exists());
    }

    public function test_anonymized_users_do_not_get_it(): void
    {
        // Anonymizing an account removes its roles on purpose: it must not get one back
        $anonymized = UserModel::factory()->create(['anonymized_at' => now()]);
        $anonymizedAndDeleted = UserModel::factory()->create(['anonymized_at' => now()]);
        $anonymizedAndDeleted->delete();
        $deleted = UserModel::factory()->create();
        $deleted->delete();

        $this->artisanCommand('users:assign-member-role', ['--dry-run' => true])
            ->expectsOutputToContain('3 usuarios se actualizarían')
            ->assertSuccessful();

        $this->artisanCommand('users:assign-member-role')
            ->expectsOutputToContain('3 usuarios actualizados')
            ->assertSuccessful();

        $this->assertSame([], $this->roleNames($anonymized));
        $this->assertSame([], $this->roleNames($anonymizedAndDeleted));
        $this->assertSame(['member'], $this->roleNames($deleted));
    }

    public function test_it_processes_users_in_batches(): void
    {
        UserModel::factory()->count(3)->create();

        $this->artisanCommand('users:assign-member-role', ['--chunk' => 2])
            ->expectsOutputToContain('5 usuarios actualizados')
            ->assertSuccessful();

        $this->assertSame(0, UserModel::query()->whereDoesntHave('roles', fn ($query) => $query->where('name', 'member'))->count());
    }

    /**
     * @return array<string>
     */
    private function roleNames(UserModel $user): array
    {
        return $user->roles()->orderBy('name')->pluck('name')->all();
    }

    private function memberSince(UserModel $user): mixed
    {
        return DB::table('user_role')
            ->where('user_id', $user->id)
            ->where('role_id', $this->member->id)
            ->value('created_at');
    }
}
