<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\DTOs\ViewedRoleDTO;
use App\Application\TwoFactor\Services\TwoFactorResetInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use App\Domain\TwoFactor\Events\TwoFactorAppChanged;
use App\Infrastructure\Authorization\Services\ActiveRoleResolver;
use App\Infrastructure\Authorization\Services\PermissionHolderFinder;
use App\Infrastructure\Impersonation\RoleViewAccount;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Support\Authorization\ManagesTwoFactorRoles;
use Tests\TestCase;

/**
 * A role that requires two-factor authentication (core#772) grants nothing while its holder
 * has it off, works as soon as it is on, and pauses again the moment it goes off. The check
 * fails closed and never touches the holder's other roles.
 */
final class TwoFactorRoleTest extends TestCase
{
    use LazilyRefreshDatabase;
    use ManagesTwoFactorRoles;

    private const string PERMISSION = 'memberships:members.view_any';

    private AuthorizationServiceInterface $authorization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorization = app(AuthorizationServiceInterface::class);
        $this->roleRequiringTwoFactor('treasury', ['admin.access', self::PERMISSION]);
    }

    public function test_role_requiring_two_factor_grants_nothing_while_it_is_off(): void
    {
        $user = $this->accountHolding(['treasury']);

        $this->assertFalse($this->authorization->can($user, self::PERMISSION));
        $this->assertFalse($this->authorization->can($user, 'admin.access'));
        $this->assertFalse($this->authorization->hasRole($user, 'treasury'));
        $this->assertSame([], $this->authorization->getPermissions($user));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_role_requiring_two_factor_works_once_it_is_on(): void
    {
        $user = $this->accountHolding(['treasury']);
        $this->turnOnTwoFactor($user);

        $this->assertTrue($this->authorization->can($user, self::PERMISSION));
        $this->assertTrue($this->authorization->hasRole($user, 'treasury'));
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_turning_it_on_activates_the_role_at_once_even_with_a_cached_answer(): void
    {
        $user = $this->accountHolding(['treasury']);
        $this->assertFalse($this->authorization->can($user, self::PERMISSION));

        $this->turnOnTwoFactor($user);

        $this->assertTrue($this->authorization->can($user, self::PERMISSION));
    }

    public function test_turning_it_off_pauses_the_role_at_once(): void
    {
        $user = $this->accountHolding(['treasury']);
        $this->turnOnTwoFactor($user);
        $this->assertTrue($this->authorization->can($user, self::PERMISSION));

        app(TwoFactorServiceInterface::class)->disable((string) $user->id);

        $this->assertFalse($this->authorization->can($user, self::PERMISSION));
    }

    public function test_a_reset_from_the_administration_pauses_the_role_at_once(): void
    {
        $user = $this->accountHolding(['treasury']);
        $this->turnOnTwoFactor($user);
        $this->assertTrue($this->authorization->can($user, self::PERMISSION));

        app(TwoFactorResetInterface::class)->reset((string) $user->id, null);

        $this->assertFalse($this->authorization->can($user, self::PERMISSION));
    }

    public function test_a_reset_while_deactivated_is_not_undone_by_restoring(): void
    {
        $user = $this->accountHolding(['treasury']);
        $this->turnOnTwoFactor($user);
        $this->assertTrue($this->authorization->can($user, self::PERMISSION));

        $user->delete();
        $this->newRequest();
        app(TwoFactorResetInterface::class)->reset((string) $user->id, null);
        $user->restore();
        $this->newRequest();

        $this->assertFalse(app(AuthorizationServiceInterface::class)->can(UserModel::query()->findOrFail($user->id), self::PERMISSION));
    }

    public function test_restoring_an_account_drops_its_cached_authorization(): void
    {
        $editor = RoleModel::query()->firstOrCreate(['name' => 'editor'], ['display_name' => 'Editor']);
        $this->givePermissions($editor, ['articles.create']);
        $user = $this->accountHolding(['editor']);
        $this->assertTrue($this->authorization->can($user, 'articles.create'));

        $user->delete();
        // Changed while deactivated, around the services: only the restore may refresh it
        $user->roles()->detach($editor->id);
        $user->restore();
        $this->newRequest();

        $this->assertFalse(app(AuthorizationServiceInterface::class)->can($user, 'articles.create'));
    }

    public function test_changing_the_app_drops_the_cached_authorization(): void
    {
        $editor = RoleModel::query()->firstOrCreate(['name' => 'editor'], ['display_name' => 'Editor']);
        $this->givePermissions($editor, ['articles.create']);
        $user = $this->accountHolding(['editor']);
        $this->assertTrue($this->authorization->can($user, 'articles.create'));
        $user->roles()->detach($editor->id);

        Event::dispatch(new TwoFactorAppChanged((string) $user->id));
        $this->newRequest();

        $this->assertFalse(app(AuthorizationServiceInterface::class)->can($user, 'articles.create'));
    }

    public function test_an_answer_read_while_the_reader_failed_is_not_cached(): void
    {
        $user = $this->accountHolding(['treasury']);
        $this->turnOnTwoFactor($user);
        $this->newRequest();
        $real = app(TwoFactorStatusReaderInterface::class);
        $this->app->instance(TwoFactorStatusReaderInterface::class, $this->failingReader());

        $this->assertFalse(app(AuthorizationServiceInterface::class)->can($user, self::PERMISSION));

        $this->app->instance(TwoFactorStatusReaderInterface::class, $real);
        $this->newRequest();

        $this->assertTrue(app(AuthorizationServiceInterface::class)->can($user, self::PERMISSION));
    }

    public function test_permission_holders_leave_out_a_paused_holder(): void
    {
        $paused = $this->accountHolding(['treasury']);
        $active = $this->accountHolding(['treasury']);
        $this->turnOnTwoFactor($active);

        $holders = app(PermissionHolderFinder::class)->usersWhoCan(self::PERMISSION);

        $this->assertTrue($holders->contains('id', $active->id));
        $this->assertFalse($holders->contains('id', $paused->id));
    }

    public function test_a_check_that_fails_leaves_the_role_paused(): void
    {
        $this->app->instance(TwoFactorStatusReaderInterface::class, $this->failingReader());

        $this->roleRequiringTwoFactor('junta')->update(['requires_two_factor' => false]);
        $this->givePermissions(RoleModel::query()->where('name', 'junta')->firstOrFail(), ['articles.create']);
        $user = $this->accountHolding(['treasury', 'junta']);

        $this->assertFalse($this->authorization->can($user, self::PERMISSION));
        $this->assertTrue($this->authorization->can($user, 'articles.create'));
    }

    public function test_other_roles_keep_working_while_one_is_paused(): void
    {
        $editor = RoleModel::query()->firstOrCreate(['name' => 'editor'], ['display_name' => 'Editor']);
        $this->givePermissions($editor, ['articles.create']);
        $user = $this->accountHolding(['treasury', 'editor']);

        $this->assertSame(['articles.create'], $this->authorization->getPermissions($user));
        $this->assertTrue($this->authorization->hasRole($user, 'editor'));
    }

    public function test_a_role_requiring_both_needs_membership_and_two_factor(): void
    {
        RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $this->roleRequiringTwoFactor('secretaria', ['secretary.manage'], requiresMembership: true);

        $memberWithoutTwoFactor = $this->accountHolding(['secretaria', 'socio']);
        $twoFactorWithoutMembership = $this->accountHolding(['secretaria']);
        $this->turnOnTwoFactor($twoFactorWithoutMembership);
        $both = $this->accountHolding(['secretaria', 'socio']);
        $this->turnOnTwoFactor($both);

        $this->assertFalse($this->authorization->can($memberWithoutTwoFactor, 'secretary.manage'));
        $this->assertFalse($this->authorization->can($twoFactorWithoutMembership, 'secretary.manage'));
        $this->assertTrue($this->authorization->can($both, 'secretary.manage'));
    }

    public function test_an_admin_whose_role_requires_it_loses_the_implicit_pass_while_it_is_off(): void
    {
        $admin = UserModel::factory()->admin()->create();
        RoleModel::query()->where('name', 'admin')->update(['requires_two_factor' => true]);

        $this->assertFalse($this->authorization->hasRole($admin, 'admin'));
        $this->assertFalse($this->authorization->can($admin, 'articles.create'));
        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('admin')));

        $this->turnOnTwoFactor($admin);

        $this->assertTrue($this->authorization->can($admin, 'articles.create'));
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_paused_by_two_factor_names_only_the_roles_it_holds_back(): void
    {
        RoleModel::query()->firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        $this->roleRequiringTwoFactor('secretaria', [], requiresMembership: true);
        $user = $this->accountHolding(['treasury', 'secretaria']);
        $resolver = app(ActiveRoleResolver::class);

        // «secretaria» also lacks membership: two-factor alone would not bring it back
        $this->assertSame(['treasury'], $resolver->pausedByTwoFactor($user));

        $this->turnOnTwoFactor($user);

        $this->assertSame([], $resolver->pausedByTwoFactor($user));
    }

    public function test_a_role_view_counts_as_having_two_factor_on(): void
    {
        $role = RoleModel::query()->where('name', 'treasury')->firstOrFail();

        DB::beginTransaction();

        try {
            $account = app(RoleViewAccount::class)->create([new ViewedRoleDTO($role->id, 'treasury', 'Treasury')]);

            $this->assertTrue($this->authorization->can($account, self::PERMISSION));
        } finally {
            DB::rollBack();
        }

        // Only the throwaway account: a real holder still needs it
        $this->assertFalse($this->authorization->can($this->accountHolding(['treasury']), self::PERMISSION));
    }

    /**
     * A new request: scoped bindings (cache tokens, two-factor reads) start again.
     */
    private function newRequest(): void
    {
        $this->app->forgetScopedInstances();
    }

    private function failingReader(): TwoFactorStatusReaderInterface
    {
        return new class implements TwoFactorStatusReaderInterface
        {
            public function isEnabled(string $userId): bool
            {
                throw new RuntimeException('store down');
            }

            public function enabledAmong(array $userIds): array
            {
                throw new RuntimeException('store down');
            }
        };
    }
}
