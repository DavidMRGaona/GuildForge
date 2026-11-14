<?php

declare(strict_types=1);

namespace Tests\Feature\Impersonation;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\DTOs\ViewedRoleDTO;
use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Impersonation\Services\ImpersonationRolesInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Impersonation\EloquentImpersonationRoles;
use App\Infrastructure\Impersonation\ImpersonatedName;
use App\Infrastructure\Impersonation\ImpersonationSessionState;
use App\Infrastructure\Impersonation\SafetyNet\RolledBackTransaction;
use App\Infrastructure\Persistence\Eloquent\Models\ImpersonationLogEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * A role view runs every request as an account built for that request alone, inside the
 * transaction the safety net always rolls back: it holds the roles chosen and is gone once the
 * response is built.
 */
final class ApplyRoleViewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_test/impersonation/whoami', static function (
            ImpersonationContextInterface $context,
            AuthorizationServiceInterface $authorization,
            AssociationMembershipServiceInterface $membership,
        ): array {
            $user = Auth::user();
            $roles = $user === null ? [] : $authorization->getRoles($user);
            sort($roles);

            return [
                'user' => Auth::id(),
                'active' => $context->isActive(),
                'roles' => $roles,
                'association' => $user !== null && $membership->isAssociationMember($user),
                'name' => ImpersonatedName::of($context->target()),
            ];
        });

        Route::middleware('web')->get('/_test/impersonation/throws', static function (): never {
            throw new RuntimeException('the page failed');
        });

        Route::middleware('web')->post('/_test/impersonation/submit', static fn (): array => ['saved' => true]);

        $this->admin = UserModel::factory()->admin()->create();
    }

    public function test_a_request_runs_as_an_account_with_the_roles(): void
    {
        $this->startViewing([$this->roleId('socio')]);

        $response = $this->getJson('/_test/impersonation/whoami');

        $this->assertNotSame($this->admin->id, $response->json('user'));
        $response
            ->assertJsonPath('roles', ['member', 'socio'])
            ->assertJsonPath('association', true)
            ->assertJsonPath('active', true);
    }

    public function test_each_request_gets_its_own_account(): void
    {
        $this->startViewing([$this->roleId('socio')]);

        $first = $this->getJson('/_test/impersonation/whoami')->json('user');
        $second = $this->getJson('/_test/impersonation/whoami')->json('user');

        $this->assertIsString($first);
        $this->assertIsString($second);
        $this->assertNotSame($first, $second);
    }

    public function test_nothing_is_left_after_the_request(): void
    {
        $this->startViewing([$this->roleId('socio')]);
        [$users, $assignments] = $this->counts();

        $this->getJson('/_test/impersonation/whoami')->assertOk();

        $this->assertSame([$users, $assignments], $this->counts());
    }

    public function test_a_request_that_throws_leaves_no_account_behind(): void
    {
        $this->startViewing([$this->roleId('socio')]);
        [$users, $assignments] = $this->counts();

        $this->getJson('/_test/impersonation/throws')->assertServerError();

        $this->assertSame([$users, $assignments], $this->counts());
    }

    public function test_a_deleted_role_revokes_the_view(): void
    {
        $this->startViewing([$this->roleId('socio')]);
        RoleModel::query()->where('name', 'socio')->delete();

        $this->assertRevokedOnTheNextRequest();
    }

    public function test_a_failure_building_the_account_revokes_the_view_and_keeps_the_entry_closed(): void
    {
        $this->startViewing([$this->roleId('socio')]);
        $this->app->instance(ImpersonationRolesInterface::class, $this->rolesThatFailInsideTheTransaction());
        [$users, $assignments] = $this->counts();

        // Closing the entry has to survive the rollback of the request's transaction
        $this->assertRevokedOnTheNextRequest();
        $this->assertSame([$users, $assignments], $this->counts());
    }

    public function test_stopping_runs_as_the_administrator_and_closes_the_entry(): void
    {
        $this->startViewing([$this->roleId('socio')]);
        [$users, $assignments] = $this->counts();

        $this->post(route('impersonation.stop'))->assertRedirect(route('home'));

        $entry = ImpersonationLogEntryModel::query()->sole();
        $this->assertNotNull($entry->ended_at);
        $this->assertSame(ImpersonationEndReason::Stopped, $entry->ended_reason);
        $this->assertFalse(app(Session::class)->has(ImpersonationSessionState::SESSION_KEY));
        $this->assertTrue(Auth::user()?->is($this->admin));
        $this->assertSame([$users, $assignments], $this->counts());
    }

    public function test_a_role_with_panel_access_opens_the_panel(): void
    {
        $role = RoleModel::query()->create(['name' => 'panel-viewers', 'display_name' => 'Panel viewers']);
        $panelAccess = PermissionModel::query()->firstOrCreate(
            ['key' => 'admin.access'],
            ['label' => 'admin.access', 'resource' => 'admin', 'action' => 'access'],
        );
        $role->permissions()->syncWithoutDetaching([$panelAccess->id]);

        $this->startViewing([(string) $role->id]);

        $this->get('/admin')->assertOk();
    }

    public function test_only_the_base_role_gets_no_panel(): void
    {
        $this->startViewing([]);

        // As an account without admin.access: back to the site, which has the band
        $this->get('/admin')->assertRedirect(route('home'));
    }

    public function test_read_only_still_refuses_site_writes(): void
    {
        $this->startViewing([$this->roleId('socio')], ImpersonationMode::ReadOnly);

        $this->postJson('/_test/impersonation/submit')
            ->assertForbidden()
            ->assertJsonPath('error', 'impersonation_read_only');
    }

    public function test_the_banner_name_is_the_roles(): void
    {
        $this->startViewing([$this->roleId('socio')]);

        $this->getJson('/_test/impersonation/whoami')->assertJsonPath('name', 'Member + Socio');
    }

    /**
     * @param  list<string>  $roleIds
     */
    private function startViewing(array $roleIds, ImpersonationMode $mode = ImpersonationMode::Simulate): void
    {
        $this->actingAs($this->admin);
        app(ImpersonationServiceInterface::class)->startWithRoles($this->admin, $roleIds, $mode);
    }

    private function roleId(string $name): string
    {
        return (string) RoleModel::query()->where('name', $name)->value('id');
    }

    /**
     * @return array{int, int}
     */
    private function counts(): array
    {
        return [UserModel::withTrashed()->count(), DB::table('user_role')->count()];
    }

    /**
     * The roles as usual outside the request's rolled back transaction (the continuation check
     * reads them before it), failing inside it, where the account is built.
     */
    private function rolesThatFailInsideTheTransaction(): ImpersonationRolesInterface
    {
        $real = app(EloquentImpersonationRoles::class);
        $transaction = app(RolledBackTransaction::class);

        return new readonly class($real, $transaction) implements ImpersonationRolesInterface
        {
            public function __construct(
                private ImpersonationRolesInterface $real,
                private RolledBackTransaction $transaction,
            ) {}

            /** @return list<ViewedRoleDTO> */
            public function find(array $ids): array
            {
                if ($this->transaction->isOpen()) {
                    throw new RuntimeException('database down');
                }

                return $this->real->find($ids);
            }

            public function baseRoleId(): ?string
            {
                return $this->real->baseRoleId();
            }

            /** @return list<ViewedRoleDTO> */
            public function offered(): array
            {
                return $this->real->offered();
            }
        };
    }

    private function assertRevokedOnTheNextRequest(): void
    {
        $this->getJson('/_test/impersonation/whoami')
            ->assertJsonPath('user', $this->admin->id)
            ->assertJsonPath('active', false);

        $entry = ImpersonationLogEntryModel::query()->sole();
        $this->assertNotNull($entry->ended_at);
        $this->assertSame(ImpersonationEndReason::Revoked, $entry->ended_reason);
        $this->assertFalse(app(Session::class)->has(ImpersonationSessionState::SESSION_KEY));
    }
}
